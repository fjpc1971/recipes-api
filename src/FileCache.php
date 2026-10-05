<?php

declare(strict_types=1);

namespace App;

use RuntimeException;

/**
 * Caché en disco: un fichero JSON por clave con su fecha de expiración.
 */
final class FileCache
{
    public function __construct(private readonly string $directory)
    {
        if (!is_dir($this->directory) && !@mkdir($this->directory, 0775, true) && !is_dir($this->directory)) {
            throw new RuntimeException('No se puede crear el directorio de caché: ' . $this->directory);
        }
    }

    public function get(string $key): mixed
    {
        $file = $this->path($key);

        if (!is_file($file)) {
            return null;
        }

        $entry = json_decode((string) @file_get_contents($file), true);

        if (!is_array($entry) || !isset($entry['expiresAt']) || $entry['expiresAt'] < time()) {
            $this->delete($key);

            return null;
        }

        return $entry['value'] ?? null;
    }

    public function set(string $key, mixed $value, int $ttlSeconds): void
    {
        $entry = json_encode(['expiresAt' => time() + $ttlSeconds, 'value' => $value], JSON_UNESCAPED_UNICODE);

        $tmp = tempnam($this->directory, 'tmp_');
        if ($tmp === false || file_put_contents($tmp, $entry) === false) {
            return;
        }

        rename($tmp, $this->path($key));
    }

    public function delete(string $key): void
    {
        @unlink($this->path($key));
    }

    private function path(string $key): string
    {
        return $this->directory . '/' . sha1($key) . '.json';
    }
}
