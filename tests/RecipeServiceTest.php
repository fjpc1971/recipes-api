<?php

declare(strict_types=1);

use App\HttpException;

test('devuelve el detalle del primer resultado', function (): void {
    $http = fakeSpoonacular();

    [$recipe, $fromCache] = newService($http)->findByName('carbonara');

    assertSame(false, $fromCache);
    assertSame(636360, $recipe['id']);
    assertSame(2, count($http->requests));
    assertSame(['query' => 'carbonara', 'number' => 1], $http->requests[0]['query']);
    assertSame('/recipes/636360/information', $http->requests[1]['path']);
});

test('la segunda búsqueda sale de caché', function (): void {
    $http = fakeSpoonacular();
    $service = newService($http);

    $service->findByName('carbonara');
    [$recipe, $fromCache] = $service->findByName('carbonara');

    assertSame(true, $fromCache);
    assertSame(636360, $recipe['id']);
    assertSame(2, count($http->requests), 'La segunda búsqueda no debe llamar a Spoonacular');
});

test('la clave de caché ignora mayúsculas y espacios', function (): void {
    $http = fakeSpoonacular();
    $service = newService($http);

    $service->findByName('Pasta Carbonara');
    [, $fromCache] = $service->findByName("  pasta   CARBONARA \t");

    assertSame(true, $fromCache);
    assertSame(2, count($http->requests));
});

test('sin resultados lanza 404 y no guarda nada en caché', function (): void {
    $http = fakeSpoonacular()->on('/recipes/complexSearch', fixture('complex_search_empty'));
    $cacheDir = tempDir();

    $e = assertThrows(HttpException::class, fn () => newService($http, $cacheDir)->findByName('xyz'));

    assertSame(404, $e->getStatusCode());
    assertSame([], glob($cacheDir . '/*'));
});

test('mapea los campos básicos', function (): void {
    [$recipe] = newService(fakeSpoonacular())->findByName('carbonara');

    assertSame('Brussels Sprout Carbonara with Fettuccini', $recipe['name']);
    assertSame(45, $recipe['readyInMinutes']);
    assertSame(4, $recipe['servings']);
    assertSame('https://img.spoonacular.com/recipes/636360-556x370.jpg', $recipe['image']);
});

test('mapea los ingredientes priorizando nameClean', function (): void {
    [$recipe] = newService(fakeSpoonacular())->findByName('carbonara');

    assertSame(3, count($recipe['ingredients']));
    assertSame([
        'name' => 'fettuccine',
        'amount' => 250.0,
        'unit' => 'gr',
        'original' => '250gr / 0.5 lb. (dry weight) of good quality fettuccini pasta',
    ], $recipe['ingredients'][0]);
    // nameClean null o ausente -> se usa name
    assertSame('garlic', $recipe['ingredients'][1]['name']);
    assertSame('eggs', $recipe['ingredients'][2]['name']);
});

test('aplana los pasos de todas las secciones de analyzedInstructions', function (): void {
    [$recipe] = newService(fakeSpoonacular())->findByName('carbonara');

    assertSame([
        'Bring a large pot of salted water to the boil and cook the pasta.',
        'Fry the bacon until crispy.',
        'Add the egg and parmesan and stir quickly.',
    ], $recipe['instructions']);
});

test('sin pasos estructurados usa las instrucciones en HTML', function (): void {
    $info = fixture('recipe_information');
    $info['analyzedInstructions'] = [];
    $info['instructions'] = '<ol><li>Cook the pasta.</li><li>Fry the <b>bacon</b>.</li></ol>';

    [$recipe] = newService(fakeSpoonacular()->on('/recipes/636360/information', $info))->findByName('carbonara');

    assertSame(['Cook the pasta.', 'Fry the bacon.'], $recipe['instructions']);
});

test('instructions es null si la receta no tiene instrucciones', function (): void {
    $info = fixture('recipe_information');
    $info['analyzedInstructions'] = [];
    $info['instructions'] = null;

    [$recipe] = newService(fakeSpoonacular()->on('/recipes/636360/information', $info))->findByName('carbonara');

    assertSame(null, $recipe['instructions']);
});

test('los campos opcionales son null si faltan', function (): void {
    $http = fakeSpoonacular()->on('/recipes/636360/information', ['id' => 636360, 'title' => 'Minimal']);

    [$recipe] = newService($http)->findByName('carbonara');

    assertSame(null, $recipe['readyInMinutes']);
    assertSame(null, $recipe['servings']);
    assertSame(null, $recipe['image']);
    assertSame(null, $recipe['instructions']);
    assertSame([], $recipe['ingredients']);
});
