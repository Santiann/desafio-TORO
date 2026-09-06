<?php

declare(strict_types=1);

namespace App\Support;

final class Validator
{
    /**
     * @var array<string, string>
     */
    private array $errors = [];

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(private readonly array $data)
    {
    }

    public function string(string $field, int $maxLength): string
    {
        $value = $this->data[$field] ?? null;

        if (!is_string($value)) {
            $this->errors[$field] = 'campo obrigatório';

            return '';
        }

        $value = trim($value);

        if ($value === '') {
            $this->errors[$field] = 'campo obrigatório';

            return '';
        }

        if (mb_strlen($value) > $maxLength) {
            $this->errors[$field] = "deve ter no máximo {$maxLength} caracteres";

            return '';
        }

        return $value;
    }

    public function assertValid(): void
    {
        if ($this->errors !== []) {
            throw HttpException::unprocessable($this->errors);
        }
    }
}
