<?php

declare(strict_types=1);

namespace App\Support;

use DateTimeImmutable;

final class Validator
{
    private const DATE_FORMATS = ['Y-m-d H:i:s', 'Y-m-d\TH:i:s', 'Y-m-d\TH:i'];

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

    public function pattern(string $field, int $maxLength, string $regex, string $message): string
    {
        $value = $this->string($field, $maxLength);

        if ($value === '' || preg_match($regex, $value) === 1) {
            return $value;
        }

        $this->errors[$field] = $message;

        return '';
    }

    public function integer(string $field, int $min, int $max): int
    {
        $value = $this->data[$field] ?? null;

        if (!is_int($value)) {
            $this->errors[$field] = 'deve ser um número inteiro';

            return 0;
        }

        if ($value < $min || $value > $max) {
            $this->errors[$field] = "deve estar entre {$min} e {$max}";

            return 0;
        }

        return $value;
    }

    public function decimal(string $field, float $min, float $max): string
    {
        $value = $this->data[$field] ?? null;

        if (!is_int($value) && !is_float($value)) {
            $this->errors[$field] = 'deve ser um número decimal';

            return '0.00';
        }

        $number = (float) $value;

        if (!($number >= $min && $number <= $max)) {
            $this->errors[$field] = sprintf('deve estar entre %.2F e %.2F', $min, $max);

            return '0.00';
        }

        return sprintf('%.2F', $number);
    }

    public function boolean(string $field): bool
    {
        $value = $this->data[$field] ?? null;

        if (!is_bool($value)) {
            $this->errors[$field] = 'deve ser true ou false';

            return false;
        }

        return $value;
    }

    public function dateTime(string $field): string
    {
        $value = $this->data[$field] ?? null;

        if (!is_string($value) || trim($value) === '') {
            $this->errors[$field] = 'campo obrigatório';

            return '';
        }

        $value = trim($value);

        foreach (self::DATE_FORMATS as $format) {
            $parsed = DateTimeImmutable::createFromFormat('!' . $format, $value);

            if ($parsed !== false && $parsed->format($format) === $value) {
                return $parsed->format('Y-m-d H:i:s');
            }
        }

        $this->errors[$field] = 'data inválida, use o formato AAAA-MM-DD HH:MM:SS';

        return '';
    }

    public function fail(string $field, string $message): void
    {
        $this->errors[$field] ??= $message;
    }

    public function assertValid(): void
    {
        if ($this->errors !== []) {
            throw HttpException::unprocessable($this->errors);
        }
    }
}
