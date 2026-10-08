<?php

declare(strict_types=1);

namespace App\Http;

final class JsonResponse
{
    /**
     * @param array<string, string> $headers
     */
    public function __construct(
        private readonly mixed $data,
        private readonly int $status = 200,
        private readonly array $headers = [],
    ) {
    }

    public static function error(string $message, int $status): self
    {
        return new self(['error' => ['code' => $status, 'message' => $message]], $status);
    }

    public function getStatus(): int
    {
        return $this->status;
    }

    public function getData(): mixed
    {
        return $this->data;
    }

    /**
     * @return array<string, string>
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * Envía la respuesta HTTP al cliente.
     */
    public function send(): void
    {
        http_response_code($this->status);
        header('Content-Type: application/json; charset=utf-8');

        foreach ($this->headers as $name => $value) {
            header($name . ': ' . $value);
        }

        // JSON_UNESCAPED_UNICODE para que los caracteres acentuados y especiales se muestren correctamente
        // JSON_UNESCAPED_SLASHES para que las URLs no se escapen con barras invertidas
        // JSON_PRETTY_PRINT para que el JSON sea legible (con saltos de línea y sangrías)
        echo json_encode($this->data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    }
}
