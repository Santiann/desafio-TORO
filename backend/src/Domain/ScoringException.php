<?php

declare(strict_types=1);

namespace App\Domain;

use RuntimeException;

final class ScoringException extends RuntimeException
{
    /**
     * @param array<string, string> $fields
     */
    private function __construct(
        public readonly string $errorCode,
        string $message,
        public readonly array $fields = [],
    ) {
        parent::__construct($message);
    }

    /**
     * @param array<string, string> $fields
     */
    public static function invalid(array $fields): self
    {
        return new self('validation_failed', 'dados inválidos', $fields);
    }

    public static function budgetExceeded(int $available, int $required): self
    {
        return new self('budget_exceeded', sprintf(
            'a venda vale %d pontos e a campanha tem apenas %d disponíveis; nada foi lançado',
            $required,
            $available,
        ));
    }
}
