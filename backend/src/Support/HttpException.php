<?php

declare(strict_types=1);

namespace App\Support;

use RuntimeException;

final class HttpException extends RuntimeException
{
    /**
     * @param array<string, string> $fields
     */
    private function __construct(
        private readonly int $status,
        private readonly string $errorCode,
        string $message,
        private readonly array $fields = [],
    ) {
        parent::__construct($message);
    }

    public static function badRequest(string $errorCode, string $message): self
    {
        return new self(400, $errorCode, $message);
    }

    public static function unauthorized(): self
    {
        return new self(401, 'unauthorized', 'token ausente ou inválido');
    }

    public static function invalidCredentials(): self
    {
        return new self(401, 'invalid_credentials', 'credenciais inválidas');
    }

    public static function forbidden(): self
    {
        return new self(403, 'forbidden', 'sem permissão para este recurso');
    }

    public static function notFound(): self
    {
        return new self(404, 'not_found', 'recurso não encontrado');
    }

    public static function methodNotAllowed(): self
    {
        return new self(405, 'method_not_allowed', 'método não permitido para esta rota');
    }

    /**
     * @param array<string, string> $fields
     */
    public static function unprocessable(array $fields): self
    {
        return self::rejected('validation_failed', 'dados inválidos', $fields);
    }

    /**
     * @param array<string, string> $fields
     */
    public static function rejected(string $errorCode, string $message, array $fields = []): self
    {
        return new self(422, $errorCode, $message, $fields);
    }

    public function status(): int
    {
        return $this->status;
    }

    public function errorCode(): string
    {
        return $this->errorCode;
    }

    /**
     * @return array<string, string>
     */
    public function fields(): array
    {
        return $this->fields;
    }
}
