<?php

declare(strict_types=1);

/**
 * Runner de tests mínimo, sin dependencias.
 *
 *   php tests/run.php                 ejecuta todos los tests
 *   php tests/run.php FileCacheTest   solo los que contengan "FileCacheTest" en el fichero o la descripción
 *                                     (texto exacto: distingue mayúsculas)
 *   php tests/run.php --live          incluye LiveTest, que llama a la API real de Spoonacular (consume cuota)
 *
 * Cada fichero tests/*Test.php registra sus casos con test('descripción', función).
 */

require __DIR__ . '/../src/autoload.php';
require __DIR__ . '/Support.php';

// Permite filtrar los tests por nombre de fichero o descripción, y activar LiveTest con --live
$args = array_slice($argv, 1); // omite el nombre del script
define('LIVE', in_array('--live', $args, true)); // activa LiveTest si se pasa --live
$filter = implode(' ', array_diff($args, ['--live'])); // texto a buscar en el nombre del fichero o la descripción del test

// Para que el runner distinga un test que falla una aserción de un test que revienta por un error inesperado
final class AssertionFailed extends Exception // Excepción lanzada cuando falla una aserción
{
}

// Variables globales para registrar los tests y el fichero de test actual
$tests = [];
$currentFile = '';

/**
 * Función que registra un test, llamada desde los ficheros de test.
 * $name es la descripción del test, $fn es la función que lo ejecuta.
 */
function test(string $name, callable $fn): void
{
    $GLOBALS['tests'][] = [$GLOBALS['currentFile'], $name, $fn]; // añade el test a la lista global
}

// Aserción de igualdad estricta, con mensaje opcional
function assertSame(mixed $expected, mixed $actual, string $message = ''): void
{
    if ($expected !== $actual) {
        throw new AssertionFailed(($message !== '' ? $message . "\n" : '')
            . 'Esperado: ' . var_export($expected, true) . "\nObtenido: " . var_export($actual, true));
    }
}

// Aserción de que una condición es verdadera, con mensaje opcional
function assertTrue(bool $condition, string $message = 'Se esperaba true'): void
{
    if (!$condition) {
        throw new AssertionFailed($message);
    }
}

// Aserción de que una condición es falsa, con mensaje opcional
function assertThrows(string $class, callable $fn): Throwable
{
    try {
        $fn();
    } catch (Throwable $e) {
        if ($e instanceof $class) {
            return $e;
        }

        throw new AssertionFailed(sprintf('Se esperaba %s, se lanzó %s: %s', $class, $e::class, $e->getMessage()));
    }

    throw new AssertionFailed(sprintf('Se esperaba %s, no se lanzó nada', $class));
}

/**
 * Línea del fichero de test donde ha fallado la excepción.
 */
function failureLocation(Throwable $e): string
{
    foreach ([['file' => $e->getFile(), 'line' => $e->getLine()], ...$e->getTrace()] as $frame) {
        if (str_ends_with($frame['file'] ?? '', 'Test.php')) {
            return basename($frame['file']) . ':' . $frame['line'];
        }
    }

    return basename($e->getFile()) . ':' . $e->getLine();
}

##################################################################

// Carga todos los tests de tests/*Test.php
foreach (glob(__DIR__ . '/*Test.php') ?: [] as $file) {
    $currentFile = basename($file, '.php');
    /*
    * Cualquier variable que cree el fichero de test es local a esa llamada y desaparece al terminar. 
    * Así cada fichero de test queda aislado del runner y del resto de ficheros de test.
    */
    (static function (string $file): void {
        require $file; // registra los tests y llama a su método "test()"
    })($file);
}

$passed = $failed = 0; // Contadores de tests pasados y fallidos
$lastFile = null; // Para mostrar el nombre del fichero de test solo una vez, antes de sus tests

/*
    * Ejecuta todos los tests registrados, filtrando por nombre de fichero o descripción si se ha pasado un filtro.
    * Muestra el resultado de cada test y un resumen final.
*/
foreach ($tests as [$file, $name, $fn]) {
    if ($filter !== '' && !str_contains($file . ' ' . $name, $filter)) {
        continue; // omite los tests que no coinciden con el filtro
    }

    if ($file !== $lastFile) {
        echo "\n" . $file . "\n"; // muestra el nombre del fichero de test solo una vez
        $lastFile = $file; // actualiza el último fichero de test mostrado
    }

    try {
        $fn(); // ejecuta el test
        $passed++; // incrementa el contador de tests pasados
        echo '  ✔ ' . $name . "\n"; // muestra el nombre del test pasado
    } catch (Throwable $e) {
        $failed++;
        $detail = $e instanceof AssertionFailed ? $e->getMessage() : $e::class . ': ' . $e->getMessage(); // detalle del fallo: aserción fallida o excepción inesperada
        echo '  ✘ ' . $name . ' (' . failureLocation($e) . ")\n";
        echo preg_replace('/^/m', '      ', $detail) . "\n";
    }
}

echo "\n" . ($failed === 0 ? 'OK' : 'FALLOS') . ": {$passed} correctos, {$failed} fallidos\n";

if (!LIVE) {
    echo "(LiveTest omitido: usa --live para probar contra la API real de Spoonacular)\n";
}

exit($failed === 0 ? 0 : 1); // devuelve 0 si todos los tests pasaron, 1 si hubo fallos
