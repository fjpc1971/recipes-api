<?php

declare(strict_types=1);

namespace App;

use RuntimeException;

/**
 * Excepción que se traduce directamente a una respuesta HTTP de error.
 */
final class HttpException extends RuntimeException
{
    public function __construct(string $message, private readonly int $statusCode = 500)
    {
        parent::__construct($message);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }
}
