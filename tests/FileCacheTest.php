<?php

declare(strict_types=1);

use App\FileCache;

test('crea el directorio si no existe', function (): void {
    $dir = tempDir();
    new FileCache($dir);

    assertTrue(is_dir($dir));
});

test('guarda y recupera valores', function (): void {
    $cache = new FileCache(tempDir());
    $cache->set('key', ['name' => 'Carbonara', 'servings' => 4], 60);

    assertSame(['name' => 'Carbonara', 'servings' => 4], $cache->get('key'));
});

test('devuelve null si la clave no existe', function (): void {
    assertSame(null, (new FileCache(tempDir()))->get('missing'));
});

test('las entradas caducadas se borran', function (): void {
    $dir = tempDir();
    $cache = new FileCache($dir);
    $cache->set('key', 'value', -1);

    assertSame(null, $cache->get('key'));
    assertSame([], glob($dir . '/*'));
});

test('delete elimina la entrada', function (): void {
    $cache = new FileCache(tempDir());
    $cache->set('key', 'value', 60);
    $cache->delete('key');

    assertSame(null, $cache->get('key'));
});

test('un fichero corrupto cuenta como fallo de caché', function (): void {
    $dir = tempDir();
    $cache = new FileCache($dir);
    $cache->set('key', 'value', 60);
    file_put_contents(glob($dir . '/*.json')[0], 'not json');

    assertSame(null, $cache->get('key'));
});
