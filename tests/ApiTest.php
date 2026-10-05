<?php

declare(strict_types=1);

/*
 * Prueba el endpoint atravesando toda la aplicación (enrutado, validación, servicio, caché y JSON).
 * Solo se sustituye la capa de red, para no depender de Spoonacular ni consumir cuota.
 */

use App\App;
use App\Http\Request;

test('la búsqueda devuelve la receta', function (): void {
    $response = apiGet(new App(newService(fakeSpoonacular())), '/api/recipes/search', ['name' => 'pasta carbonara']);

    assertSame(200, $response->getStatus());
    assertSame('MISS', $response->getHeaders()['X-Cache']);
    assertSame([
        'data' => [
            'id' => 636360,
            'name' => 'Brussels Sprout Carbonara with Fettuccini',
            'readyInMinutes' => 45,
            'servings' => 4,
            'ingredients' => [
                ['name' => 'fettuccine', 'amount' => 250, 'unit' => 'gr', 'original' => '250gr / 0.5 lb. (dry weight) of good quality fettuccini pasta'],
                ['name' => 'garlic', 'amount' => 2, 'unit' => 'large', 'original' => '2 x large cloves of garlic chopped finely'],
                ['name' => 'eggs', 'amount' => 2.5, 'unit' => '', 'original' => '2 x eggs, beaten'],
            ],
            'instructions' => [
                'Bring a large pot of salted water to the boil and cook the pasta.',
                'Fry the bacon until crispy.',
                'Add the egg and parmesan and stir quickly.',
            ],
            'image' => 'https://img.spoonacular.com/recipes/636360-556x370.jpg',
        ],
    ], responseBody($response));
});

test('una búsqueda repetida sale de caché (X-Cache: HIT)', function (): void {
    $http = fakeSpoonacular();
    $app = new App(newService($http));

    $first = apiGet($app, '/api/recipes/search', ['name' => 'pasta carbonara']);
    $second = apiGet($app, '/api/recipes/search', ['name' => 'Pasta  Carbonara']);

    assertSame('HIT', $second->getHeaders()['X-Cache']);
    assertSame(responseBody($first), responseBody($second));
    assertSame(2, count($http->requests));
});

test('sin name devuelve 400', function (): void {
    $http = fakeSpoonacular();
    $app = new App(newService($http));

    assertErrorResponse(400, apiGet($app, '/api/recipes/search'));
    assertErrorResponse(400, apiGet($app, '/api/recipes/search', ['name' => '   ']));
    assertSame([], $http->requests);
});

test('un name de más de 100 caracteres devuelve 400', function (): void {
    $app = new App(newService(fakeSpoonacular()));

    assertErrorResponse(400, apiGet($app, '/api/recipes/search', ['name' => str_repeat('a', 101)]));
});

test('sin resultados devuelve 404', function (): void {
    $app = new App(newService(fakeSpoonacular()->on('/recipes/complexSearch', fixture('complex_search_empty'))));

    assertErrorResponse(404, apiGet($app, '/api/recipes/search', ['name' => 'xyz']));
});

test('con la cuota agotada devuelve 503', function (): void {
    $http = fakeSpoonacular()->on('/recipes/complexSearch', new RuntimeException('Your daily points limit has been reached', 402));

    assertErrorResponse(503, apiGet(new App(newService($http)), '/api/recipes/search', ['name' => 'carbonara']));
});

test('un error inesperado devuelve 500 sin mostrar detalles', function (): void {
    // Spoonacular devuelve un id sin detalle configurado en el fake -> LogicException genérica
    $app = new App(newService(fakeSpoonacular()->on('/recipes/complexSearch', ['results' => [['id' => 999]]])));

    $previousLog = ini_set('error_log', '/dev/null'); // silencia el error_log durante el test
    try {
        $response = apiGet($app, '/api/recipes/search', ['name' => 'carbonara']);
    } finally {
        ini_set('error_log', (string) $previousLog);
    }

    assertErrorResponse(500, $response);
    assertSame('Error interno del servidor', responseBody($response)['error']['message']);
});

test('una ruta desconocida devuelve 404', function (): void {
    assertErrorResponse(404, apiGet(new App(newService(fakeSpoonacular())), '/api/unknown'));
});

test('un método distinto de GET devuelve 405', function (): void {
    $app = new App(newService(fakeSpoonacular()));

    assertErrorResponse(405, $app->handle(new Request('POST', '/api/recipes/search')));
});
