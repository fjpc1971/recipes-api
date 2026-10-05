<?php

declare(strict_types=1);

use App\HttpException;
use App\SpoonacularClient;

test('envía la API key en cabecera y no en la URL', function (): void {
    $http = (new FakeHttpClient())->on('/recipes/complexSearch', fixture('complex_search'));
    $client = new SpoonacularClient($http, 'secret-key', 'https://api.example.test/');

    $results = $client->searchRecipes('carbonara');

    assertSame(2, count($results));
    assertSame('https://api.example.test/recipes/complexSearch', $http->requests[0]['url']);
    assertSame(['x-api-key' => 'secret-key'], $http->requests[0]['headers']);
    assertTrue(!array_key_exists('apiKey', $http->requests[0]['query']), 'La API key no debe ir en la query');
});

test('pide el detalle de la receta sin información nutricional', function (): void {
    $http = (new FakeHttpClient())->on('/recipes/636360/information', fixture('recipe_information'));
    $client = new SpoonacularClient($http, 'secret-key', 'https://api.example.test');

    $info = $client->getRecipeInformation(636360);

    assertSame(636360, $info['id']);
    assertSame(['includeNutrition' => 'false'], $http->requests[0]['query']);
});

test('sin API key falla con 500 sin llamar a Spoonacular', function (): void {
    $http = new FakeHttpClient();
    $client = new SpoonacularClient($http, '', 'https://api.example.test');

    $e = assertThrows(HttpException::class, fn () => $client->searchRecipes('carbonara'));

    assertSame(500, $e->getStatusCode());
    assertSame([], $http->requests);
});

// Status recibido de Spoonacular (0 = sin conexión) => status que devuelve nuestra API
foreach ([401 => 502, 402 => 503, 404 => 502, 500 => 502, 0 => 504] as $upstream => $expected) {
    test("traduce el error {$upstream} de Spoonacular a {$expected}", function () use ($upstream, $expected): void {
        $http = (new FakeHttpClient())->on('/recipes/complexSearch', new RuntimeException('error', $upstream));
        $client = new SpoonacularClient($http, 'secret-key', 'https://api.example.test');

        $e = assertThrows(HttpException::class, fn () => $client->searchRecipes('carbonara'));

        assertSame($expected, $e->getStatusCode());
    });
}
