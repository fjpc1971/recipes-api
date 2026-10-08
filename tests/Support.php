<?php

declare(strict_types=1);

/**
 * Dobles de test y utilidades compartidas por los tests.
 */

use App\App;
use App\FileCache;
use App\Http\HttpClient;
use App\Http\JsonResponse;
use App\Http\Request;
use App\RecipeService;
use App\SpoonacularClient;

/**
 * Sustituye a cURL: devuelve respuestas preparadas según la ruta y registra las peticiones.
 */
final class FakeHttpClient extends HttpClient
{
    /** @var list<array{url: string, path: string, query: array<string, scalar>, headers: array<string, string>}> */
    public array $requests = [];

    /** @var array<string, array<mixed>|Throwable> */
    private array $responses = [];

    /**
     * @param array<mixed>|Throwable $response Respuesta (o excepción a lanzar) para esa ruta
     */
    public function on(string $path, array|Throwable $response): self
    {
        $this->responses[$path] = $response;

        return $this;
    }

    public function getJson(string $url, array $query = [], array $headers = []): array
    {
        $path = (string) parse_url($url, PHP_URL_PATH);
        $this->requests[] = ['url' => $url, 'path' => $path, 'query' => $query, 'headers' => $headers];

        // LogicException (y no RuntimeException) para que SpoonacularClient no la confunda con un error HTTP
        $response = $this->responses[$path] ?? throw new LogicException('Petición no esperada: ' . $path);

        if ($response instanceof Throwable) {
            throw $response;
        }

        return $response;
    }
}

/**
 * @return array<mixed> Respuesta de ejemplo de Spoonacular guardada en tests/Fixtures
 */
function fixture(string $name): array
{
    return json_decode((string) file_get_contents(__DIR__ . '/Fixtures/' . $name . '.json'), true, flags: JSON_THROW_ON_ERROR);
}

/**
 * Directorio temporal único; se borra al terminar la ejecución.
 */
function tempDir(): string
{
    $dir = sys_get_temp_dir() . '/recipes-test-' . bin2hex(random_bytes(4));

    register_shutdown_function(static function () use ($dir): void {
        array_map('unlink', glob($dir . '/*') ?: []);
        @rmdir($dir);
    });

    return $dir;
}

/**
 * Spoonacular falso que responde a la búsqueda de "carbonara" y al detalle de la receta 636360.
 */
function fakeSpoonacular(): FakeHttpClient
{
    return (new FakeHttpClient())
        ->on('/recipes/complexSearch', fixture('complex_search'))
        ->on('/recipes/636360/information', fixture('recipe_information'));
}

// Crea un RecipeService con un SpoonacularClient que use el FakeHttpClient y un FileCache temporal
function newService(FakeHttpClient $http, ?string $cacheDir = null): RecipeService
{
    return new RecipeService(
        new SpoonacularClient($http, 'test-key', 'https://api.example.test'),
        new FileCache($cacheDir ?? tempDir()),
        3600,
    );
}

/**
 * Hace una petición GET a la API y devuelve la respuesta.
 */
function apiGet(App $app, string $path, array $query = []): JsonResponse
{
    return $app->handle(new Request('GET', $path, $query));
}

/**
 * Decodifica la respuesta tal y como la recibiría un cliente HTTP.
 *
 * @return array<mixed>
 */
function responseBody(JsonResponse $response): array
{
    return json_decode((string) json_encode($response->getData()), true, flags: JSON_THROW_ON_ERROR);
}

function assertErrorResponse(int $status, JsonResponse $response): void
{
    $body = responseBody($response);

    assertSame($status, $response->getStatus());
    assertSame($status, $body['error']['code']);
    assertTrue(is_string($body['error']['message']), 'El error debe incluir un mensaje');
}
