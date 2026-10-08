<?php

declare(strict_types=1);

namespace App;

/**
 * Lógica de negocio: buscar una receta por nombre (primer resultado) usando caché,
 * y convertirla del formato de Spoonacular al que expone nuestra API.
 */
final class RecipeService
{
    public function __construct(
        private readonly SpoonacularClient $client,
        private readonly FileCache $cache,
        private readonly int $cacheTtl,
    ) {
    }

    /**
     * @return array{0: array<string, mixed>, 1: bool} Receta y si se ha servido desde caché
     */
    public function findByName(string $name): array
    {
        $cacheKey = 'recipe_search:' . self::normalize($name);

        $cached = $this->cache->get($cacheKey);
        if (is_array($cached)) {
            return [$cached, true];
        }

        $results = $this->client->searchRecipes($name, 1); // Buscar en Spoonacular (solo el primer resultado)
        if ($results === []) {
            throw new HttpException(sprintf('No se ha encontrado ninguna receta para "%s"', $name), 404);
        }

        $recipe = self::mapRecipe($this->client->getRecipeInformation((int) $results[0]['id'])); // Convertir la respuesta de Spoonacular al formato de nuestra API

        $this->cache->set($cacheKey, $recipe, $this->cacheTtl); // Guardar en caché la receta completa para la próxima búsqueda

        return [$recipe, false];
    }

    /**
     * Normaliza el nombre de la receta para usarlo como clave de caché.
     */
    private static function normalize(string $name): string
    {
        return mb_strtolower((string) preg_replace('/\s+/u', ' ', trim($name)));
    }

    /**
     * @param array<string, mixed> $info Respuesta de /recipes/{id}/information
     *
     * @return array<string, mixed>
     */
    private static function mapRecipe(array $info): array
    {
        return [
            'id' => (int) $info['id'],
            'name' => (string) ($info['title'] ?? ''),
            'readyInMinutes' => isset($info['readyInMinutes']) ? (int) $info['readyInMinutes'] : null,
            'servings' => isset($info['servings']) ? (int) $info['servings'] : null,
            'ingredients' => array_map(static fn (array $i): array => [ // Array_map para convertir cada ingrediente al formato de nuestra API
                'name' => (string) ($i['nameClean'] ?? $i['name'] ?? ''),
                'amount' => isset($i['amount']) ? $i['amount'] + 0 : null,
                'unit' => (string) ($i['unit'] ?? ''),
                'original' => (string) ($i['original'] ?? ''),
            ], array_values($info['extendedIngredients'] ?? [])), // array_values para reindexar el array y evitar claves no numéricas
            'instructions' => self::mapInstructions($info),
            'image' => !empty($info['image']) ? (string) $info['image'] : null,
        ];
    }

    /**
     * Prioriza los pasos estructurados (analyzedInstructions); si no hay, usa el texto libre.
     * Devuelve null si la receta no tiene instrucciones.
     * 
     * @param array<string, mixed> $info
     *
     * @return list<string>|null
     */
    private static function mapInstructions(array $info): ?array
    {
        $steps = [];

        foreach ($info['analyzedInstructions'] ?? [] as $section) {
            foreach ($section['steps'] ?? [] as $step) {
                $text = trim((string) ($step['step'] ?? ''));
                if ($text !== '') {
                    $steps[] = $text;
                }
            }
        }

        if ($steps === [] && !empty($info['instructions'])) {
            $text = strip_tags(str_replace(['</li>', '</p>', '<br>', '<br/>', '<br />'], "\n", (string) $info['instructions'])); // Reemplazar etiquetas HTML de salto de línea por saltos de línea reales, y eliminar otras etiquetas
            $steps = array_values(array_filter(array_map('trim', explode("\n", $text)), static fn (string $l): bool => $l !== '')); // Dividir el texto libre en líneas, eliminar vacías y reindexar
        }

        return $steps !== [] ? $steps : null;
    }
}
