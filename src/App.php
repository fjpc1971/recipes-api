<?php

declare(strict_types=1);

namespace App;

use App\Http\HttpClient;
use App\Http\JsonResponse;
use App\Http\Request;
use Throwable;

/**
 * Punto de entrada de la aplicación: construye dependencias, enruta y gestiona errores.
 */
final class App
{
    private const MAX_NAME_LENGTH = 100; // Límite de caracteres para el parámetro "name" en la búsqueda de recetas

    public function __construct(private readonly RecipeService $recipeService)
    {
    }

    /**
     * Construye la aplicación con las dependencias reales, configuradas por variables de entorno.
     */
    public static function fromEnvironment(): self
    {
        $ttl = getenv('CACHE_TTL');

        return new self(new RecipeService(
            new SpoonacularClient(
                new HttpClient(),
                getenv('SPOONACULAR_API_KEY') ?: '',
                getenv('SPOONACULAR_BASE_URL') ?: 'https://api.spoonacular.com',
            ),
            new FileCache(getenv('CACHE_DIR') ?: sys_get_temp_dir() . '/recipes-cache'),
            is_numeric($ttl) ? (int) $ttl : 86400,
        ));
    }

    /**
     * Comprueba ruta y método, llama al servicio y devuelve la respuesta JSON.
     */
    public function handle(Request $request): JsonResponse
    {
        try {
            if ($request->getPath() !== '/api/recipes/search') {
                throw new HttpException('Ruta no encontrada', 404);
            }

            if ($request->getMethod() !== 'GET') {
                throw new HttpException('Método no permitido', 405);
            }

            return $this->searchRecipe($request);
        } catch (HttpException $e) { //Respuesta de error controlada, con mensaje y código HTTP
            return JsonResponse::error($e->getMessage(), $e->getStatusCode());
        } catch (Throwable $e) { //Error inesperado: log y respuesta genérica
            error_log((string) $e);

            return JsonResponse::error('Error interno del servidor', 500);
        }
    }

    /**
     * GET /api/recipes/search?name={nombre}
     */
    private function searchRecipe(Request $request): JsonResponse
    {
        $name = trim((string) $request->query('name', ''));

        if ($name === '') {
            throw new HttpException('El parámetro "name" es obligatorio', 400);
        }

        if (mb_strlen($name) > self::MAX_NAME_LENGTH) {
            throw new HttpException(sprintf('El parámetro "name" no puede superar %d caracteres', self::MAX_NAME_LENGTH), 400);
        }

        [$recipe, $fromCache] = $this->recipeService->findByName($name); // $recipe = array data, y $fromCache = bool indicando si se ha servido desde caché

        return new JsonResponse(['data' => $recipe], 200, ['X-Cache' => $fromCache ? 'HIT' : 'MISS']); //Instancia de JsonResponse con datos, status 200 y cabecera X-Cache indicando si se ha servido desde caché
    }
}
