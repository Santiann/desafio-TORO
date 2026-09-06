<?php

declare(strict_types=1);

namespace App\Support;

use Dotenv\Dotenv;
use RuntimeException;

final class Env
{
    public static function load(string $directory): void
    {
        Dotenv::createImmutable($directory)->safeLoad();
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);

        if ($value === false || $value === null || $value === '') {
            return $default;
        }

        return (string) $value;
    }

    public static function required(string $key): string
    {
        $value = self::get($key);

        if ($value === null) {
            throw new RuntimeException("missing environment variable: {$key}");
        }

        return $value;
    }

    public static function int(string $key, int $default): int
    {
        $value = self::get($key);

        return $value === null ? $default : (int) $value;
    }
}
