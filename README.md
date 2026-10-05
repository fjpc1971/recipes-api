# API de Recetas

Servicio REST en PHP 8.3 (sin frameworks) que busca una receta por nombre en [Spoonacular](https://spoonacular.com/food-api) y devuelve el primer resultado.

## Puesta en marcha

Todos los comandos `docker compose` de este README se ejecutan desde la raíz del proyecto, donde está `docker-compose.yml`. Desde otra carpeta fallan con `no configuration file provided: not found` (o hay que indicar el fichero con `-f ruta/al/docker-compose.yml`).

```bash
cp .env.example .env        # y rellenar SPOONACULAR_API_KEY
docker compose up -d --build
```

La API queda disponible en `http://localhost:8080`.

## Documentación (Swagger)

- Swagger UI: http://localhost:8080/docs/ (permite probar los endpoints desde el navegador)
- Especificación OpenAPI 3: http://localhost:8080/openapi.yaml ([public/openapi.yaml](public/openapi.yaml))

## Tests

Con la API levantada (`docker compose up -d`):

```bash
docker compose exec api php tests/run.php            # todos
docker compose exec api php tests/run.php FileCacheTest   # solo los tests de ese fichero
```

El filtro busca el texto exacto (distingue mayúsculas) en el nombre del fichero y en la descripción del test.

Se ejecutan en el propio contenedor de la API con un runner mínimo (`tests/run.php`), sin PHPUnit ni Composer: el proyecto no tiene ninguna dependencia. Muestra `✔`/`✘` por test y termina con código 1 si alguno falla.

- `FileCacheTest`, `SpoonacularClientTest`, `RecipeServiceTest`: caché en ficheros, cliente de Spoonacular (incluida la traducción de errores) y servicio de recetas (incluido el mapeo del formato de Spoonacular).
- `ApiTest`: llama al endpoint atravesando toda la aplicación (enrutado y validación → servicio → caché → JSON).

Solo se sustituye la capa HTTP saliente por un `FakeHttpClient` que devuelve respuestas con el formato de Spoonacular, guardadas en `tests/Fixtures`, así que los tests no consumen cuota ni dependen de la red.

### Test contra la API real de Spoonacular

```bash
docker compose exec api php tests/run.php --live Live   # solo el test real
docker compose exec api php tests/run.php --live        # todos + el test real
```

`LiveTest` hace una búsqueda real (2 peticiones, consume cuota) y comprueba que la respuesta mantiene el formato esperado, sin fijarse en los valores concretos. Necesita `SPOONACULAR_API_KEY` en `.env` y sin `--live` se omite.

## Endpoints

### `GET /api/recipes/search?name={nombre}`

```bash
curl "http://localhost:8080/api/recipes/search?name=pasta%20carbonara"
```

```json
{
    "data": {
        "id": 636360,
        "name": "Brussels Sprout Carbonara with Fettuccini",
        "readyInMinutes": 45,
        "servings": 4,
        "ingredients": [
            { "name": "shallots", "amount": 2, "unit": "", "original": "2 x shallots chopped finely" }
        ],
        "instructions": ["Paso 1...", "Paso 2..."],
        "image": "https://img.spoonacular.com/recipes/636360-556x370.jpg"
    }
}
```

- `instructions` es `null` si la receta no tiene instrucciones.
- La cabecera `X-Cache` indica si la respuesta viene de caché (`HIT`) o de Spoonacular (`MISS`).

### Errores

Todos los errores tienen el formato `{"error": {"code": 404, "message": "..."}}`.

| Código | Motivo |
|--------|--------|
| 400 | Falta el parámetro `name` o supera los 100 caracteres |
| 404 | No hay recetas para esa búsqueda, o la ruta no existe |
| 405 | Método HTTP no permitido |
| 502 | Error de Spoonacular (por ejemplo, API key no válida) |
| 503 | Cuota diaria de Spoonacular agotada |
| 504 | No se pudo conectar con Spoonacular |

## Estructura

```
public/
├── index.php                 Front controller
├── openapi.yaml              Especificación OpenAPI
└── docs/index.html           Swagger UI
tests/
├── run.php                   Runner y aserciones
├── Support.php               FakeHttpClient y utilidades compartidas
├── *Test.php                 Tests (LiveTest.php: contra la API real, solo con --live)
└── Fixtures/                 Respuestas de ejemplo de Spoonacular
src/
├── App.php                   Rutas, validación de la entrada y gestión de errores; fromEnvironment() lee las variables de entorno y construye las dependencias
├── RecipeService.php         Lógica de búsqueda y caché; convierte el formato de Spoonacular al de la API
├── SpoonacularClient.php     Llamadas a la API externa y traducción de sus errores
├── FileCache.php             Caché en ficheros
├── HttpException.php         Excepción con código HTTP que se traduce a respuesta de error
├── autoload.php              Autoloader PSR-4 (App\ -> src/)
└── Http/                     Request, JsonResponse y HttpClient (cURL)
```

## Caché

Los resultados se guardan en disco (`FileCache`, un fichero JSON por búsqueda) durante `CACHE_TTL` segundos, 24 h por defecto. La clave se normaliza, así que `Pasta  Carbonara` y `pasta carbonara` usan la misma entrada. Los datos viven en un volumen Docker y sobreviven a los reinicios del contenedor.

Para borrar la caché: `docker compose down -v`.

Para cambiar de backend (Redis, APCu...) basta con una clase con los mismos métodos `get`/`set` que `FileCache`, y usarla en `RecipeService.php` y `App.php`.

## Variables de entorno

| Variable | Por defecto | Descripción |
|----------|-------------|-------------|
| `SPOONACULAR_API_KEY` | — | API key de Spoonacular (obligatoria) |
| `SPOONACULAR_BASE_URL` | `https://api.spoonacular.com` | URL base de la API |
| `CACHE_TTL` | `86400` | Tiempo de vida de la caché, en segundos |
| `CACHE_DIR` | `/var/cache/recipes` | Directorio de la caché |
