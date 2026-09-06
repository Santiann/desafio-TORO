<?php

declare(strict_types=1);

namespace App\Http;

use App\Http\Middleware\Middleware;

final class Route
{
    public readonly string $regex;

    /**
     * @param callable(Request): Response $handler
     * @param Middleware[] $middleware
     */
    public function __construct(
        public readonly string $method,
        string $pattern,
        public readonly mixed $handler,
        public readonly array $middleware = [],
    ) {
        $this->regex = self::compile($pattern);
    }

    private static function compile(string $pattern): string
    {
        $segments = preg_split(
            '/(\{[a-zA-Z_][a-zA-Z0-9_]*\})/',
            $pattern,
            -1,
            PREG_SPLIT_DELIM_CAPTURE,
        ) ?: [];

        $regex = '';

        foreach ($segments as $segment) {
            if ($segment === '') {
                continue;
            }

            $regex .= str_starts_with($segment, '{')
                ? '(?P<' . substr($segment, 1, -1) . '>[^/]+)'
                : preg_quote($segment, '#');
        }

        return '#^' . $regex . '$#';
    }
}
