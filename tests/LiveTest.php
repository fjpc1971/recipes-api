<?php

declare(strict_types=1);

/*
 * Test de humo contra la API real de Spoonacular: comprueba que cURL funciona y que la respuesta
 * sigue teniendo el formato que espera el mapeo. Solo comprueba la estructura, no los valores,
 * porque los datos de Spoonacular pueden cambiar.
 *
 * Solo se ejecuta con --live y necesita SPOONACULAR_API_KEY. Consume cuota (2 peticiones).
 */

use App\App;
use App\FileCache;
use App\Http\HttpClient;
use App\RecipeService;
use App\SpoonacularClient;

if (!LIVE) {
    return;
}

test('una búsqueda real devuelve una receta con el formato esperado', function (): void {
    // Caché vacía, para que la petición llegue siempre a Spoonacular
    $app = new App(new RecipeService(
        new SpoonacularClient(
            new HttpClient(),
            getenv('SPOONACULAR_API_KEY') ?: '',
            getenv('SPOONACULAR_BASE_URL') ?: 'https://api.spoonacular.com',
        ),
        new FileCache(tempDir()),
        60,
    ));

    $response = apiGet($app, '/api/recipes/search', ['name' => 'pasta carbonara']);
    $body = responseBody($response);

    assertSame(200, $response->getStatus(), 'Respuesta: ' . json_encode($body, JSON_UNESCAPED_UNICODE));
    assertSame('MISS', $response->getHeaders()['X-Cache']);

    $recipe = $body['data'];
    assertSame(['id', 'name', 'readyInMinutes', 'servings', 'ingredients', 'instructions', 'image'], array_keys($recipe));
    assertTrue(is_int($recipe['id']), 'id debe ser un entero');
    assertTrue(is_string($recipe['name']) && $recipe['name'] !== '', 'name no debe estar vacío');
    assertTrue(is_int($recipe['readyInMinutes']), 'readyInMinutes debe ser un entero');
    assertTrue(is_int($recipe['servings']), 'servings debe ser un entero');
    assertTrue(is_string($recipe['image']), 'image debe ser una URL');

    // Si Spoonacular renombrara campos, el mapeo devolvería listas vacías o null sin dar error
    assertTrue($recipe['ingredients'] !== [], 'ingredients no debe estar vacío');
    foreach ($recipe['ingredients'] as $ingredient) {
        assertSame(['name', 'amount', 'unit', 'original'], array_keys($ingredient));
        assertTrue($ingredient['name'] !== '', 'Ingrediente sin nombre: ' . json_encode($ingredient));
    }

    assertTrue(is_array($recipe['instructions']) && $recipe['instructions'] !== [], 'instructions no debe estar vacío');
});
