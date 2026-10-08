<?php

declare(strict_types=1);

namespace App;

use App\Http\HttpClient;
use RuntimeException;

/**
 * Acceso a la API de Spoonacular.
 */
class SpoonacularClient
{
    public function __construct(
        private readonly HttpClient $http,
        private readonly string $apiKey,
        private readonly string $baseUrl,
    ) {
    }

    /**
     * @return list<array<string, mixed>> Resultados de la búsqueda (id, title, image...)
     */
    public function searchRecipes(string $query, int $number = 1): array
    {
        $data = $this->request('/recipes/complexSearch', ['query' => $query, 'number' => $number]);

        return $data['results'] ?? [];
    }

    /**
     * @return array<string, mixed> Información completa de la receta
     */
    public function getRecipeInformation(int $id): array
    {
        return $this->request('/recipes/' . $id . '/information', ['includeNutrition' => 'false']); //
    }

    /**
     * @param array<string, scalar> $query
     * 
     * @return array<mixed>
     */
    private function request(string $path, array $query): array
    {
        if ($this->apiKey === '') {
            throw new HttpException('SPOONACULAR_API_KEY no está configurada', 500);
        }

        try {
            // La key va en cabecera para que no aparezca en URLs ni logs
            return $this->http->getJson(rtrim($this->baseUrl, '/') . $path, $query, ['x-api-key' => $this->apiKey]);
        } catch (RuntimeException $e) {
            // El código de la excepción de HttpClient es el status HTTP recibido (0 si no hubo respuesta)
            throw match ($e->getCode()) {
                401 => new HttpException('API key de Spoonacular no válida', 502),
                402 => new HttpException('Cuota diaria de Spoonacular agotada', 503),
                404 => new HttpException('Recurso no encontrado en Spoonacular', 502),
                0 => new HttpException('No se pudo conectar con Spoonacular', 504),
                default => new HttpException('Error en Spoonacular: ' . $e->getMessage(), 502),
            };
        }
    }
}
