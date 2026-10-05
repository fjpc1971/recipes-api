<?php

declare(strict_types=1);

namespace App\Http;

use RuntimeException;

/**
 * Cliente HTTP genérico basado en cURL para consumir APIs JSON.
 */
class HttpClient
{
    public function __construct(private readonly int $timeoutSeconds = 10)
    {
    }

    /**
     * @param array<string, scalar> $query
     * @param array<string, string> $headers
     *
     * @return array<mixed>
     *
     * @throws RuntimeException Con el status HTTP recibido como código (0 si no hubo respuesta)
     */
    public function getJson(string $url, array $query = [], array $headers = []): array
    {
        if ($query !== []) {
            $url .= '?' . http_build_query($query);
        }

        $headerLines = ['Accept: application/json'];
        foreach ($headers as $name => $value) {
            $headerLines[] = $name . ': ' . $value;
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => $this->timeoutSeconds,
            CURLOPT_TIMEOUT => $this->timeoutSeconds,
            CURLOPT_HTTPHEADER => $headerLines,
        ]);

        $body = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($body === false) {
            throw new RuntimeException('Error de conexión: ' . $error, 0);
        }

        $data = json_decode((string) $body, true);

        if ($status >= 400) {
            $message = is_array($data) && isset($data['message']) ? (string) $data['message'] : 'HTTP ' . $status;
            throw new RuntimeException($message, $status);
        }

        if (!is_array($data)) {
            throw new RuntimeException('Respuesta JSON no válida', $status);
        }

        return $data;
    }
}
