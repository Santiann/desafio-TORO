<?php

declare(strict_types=1);

namespace App\Http;

use App\Support\HttpException;

final class Response
{
    /**
     * @param array<string, mixed> $body
     */
    private function __construct(
        private readonly int $status,
        private readonly array $body,
    ) {
    }

    /**
     * @param array<string, mixed> $body
     */
    public static function json(array $body, int $status = 200): self
    {
        return new self($status, $body);
    }

    public static function error(HttpException $exception): self
    {
        $error = [
            'code' => $exception->errorCode(),
            'message' => $exception->getMessage(),
        ];

        if ($exception->fields() !== []) {
            $error['fields'] = $exception->fields();
        }

        return new self($exception->status(), ['error' => $error]);
    }

    public static function internalError(): self
    {
        return new self(500, ['error' => ['code' => 'internal_error', 'message' => 'erro interno']]);
    }

    public function status(): int
    {
        return $this->status;
    }

    /**
     * @return array<string, mixed>
     */
    public function body(): array
    {
        return $this->body;
    }

    public function send(): void
    {
        if (!headers_sent()) {
            http_response_code($this->status);
            header('Content-Type: application/json; charset=utf-8');
        }

        echo json_encode($this->body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
