<?php

declare(strict_types=1);

/**
 * Runner de tests mínimo, sin dependencias.
 *
 *   php tests/run.php            ejecuta todos los tests
 *   php tests/run.php caché      solo los que contengan "caché" en el fichero o la descripción
 *   php tests/run.php --live     incluye LiveTest, que llama a la API real de Spoonacular (consume cuota)
 *
 * Cada fichero tests/*Test.php registra sus casos con test('descripción', función).
 */

require __DIR__ . '/../src/autoload.php';
require __DIR__ . '/Support.php';

$args = array_slice($argv, 1);
define('LIVE', in_array('--live', $args, true));
$filter = implode(' ', array_diff($args, ['--live']));

final class AssertionFailed extends Exception
{
}

/** @var list<array{string, string, callable}> */
$tests = [];
$currentFile = '';

function test(string $name, callable $fn): void
{
    $GLOBALS['tests'][] = [$GLOBALS['currentFile'], $name, $fn];
}

function assertSame(mixed $expected, mixed $actual, string $message = ''): void
{
    if ($expected !== $actual) {
        throw new AssertionFailed(($message !== '' ? $message . "\n" : '')
            . 'Esperado: ' . var_export($expected, true) . "\nObtenido: " . var_export($actual, true));
    }
}

function assertTrue(bool $condition, string $message = 'Se esperaba true'): void
{
    if (!$condition) {
        throw new AssertionFailed($message);
    }
}

/**
 * @template T of Throwable
 *
 * @param class-string<T> $class
 *
 * @return T La excepción lanzada, para poder comprobar sus datos
 */
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

foreach (glob(__DIR__ . '/*Test.php') ?: [] as $file) {
    $currentFile = basename($file, '.php');
    (static function (string $file): void {
        require $file;
    })($file);
}

$passed = $failed = 0;
$lastFile = null;

foreach ($tests as [$file, $name, $fn]) {
    if ($filter !== '' && !str_contains($file . ' ' . $name, $filter)) {
        continue;
    }

    if ($file !== $lastFile) {
        echo "\n" . $file . "\n";
        $lastFile = $file;
    }

    try {
        $fn();
        $passed++;
        echo '  ✔ ' . $name . "\n";
    } catch (Throwable $e) {
        $failed++;
        $detail = $e instanceof AssertionFailed ? $e->getMessage() : $e::class . ': ' . $e->getMessage();
        echo '  ✘ ' . $name . ' (' . failureLocation($e) . ")\n";
        echo preg_replace('/^/m', '      ', $detail) . "\n";
    }
}

echo "\n" . ($failed === 0 ? 'OK' : 'FALLOS') . ": {$passed} correctos, {$failed} fallidos\n";

if (!LIVE) {
    echo "(LiveTest omitido: usa --live para probar contra la API real de Spoonacular)\n";
}

exit($failed === 0 ? 0 : 1);
