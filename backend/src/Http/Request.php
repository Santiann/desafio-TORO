<?php

declare(strict_types=1);

namespace App\Http;

use App\Domain\Identity;
use App\Support\HttpException;
use LogicException;

final class Request
{
    /**
     * @param array<string, string> $query
     * @param array<string, mixed> $body
     * @param array<string, string> $headers
     * @param array<string, string> $params
     */
    private function __construct(
        public readonly string $method,
        public readonly string $path,
        private readonly array $query,
        private readonly array $body,
        private readonly array $headers,
        private readonly array $params = [],
        private readonly ?Identity $identity = null,
    ) {
    }

    public static function fromGlobals(): self
    {
        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        $path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
        $path = is_string($path) && $path !== '' ? $path : '/';

        if ($path !== '/') {
            $path = rtrim($path, '/');
        }

        return new self($method, $path, self::readQuery(), self::readBody(), self::readHeaders());
    }

    /**
     * @param array<string, string> $query
     * @param array<string, mixed> $body
     * @param array<string, string> $headers
     */
    public static function create(
        string $method,
        string $path,
        array $query = [],
        array $body = [],
        array $headers = [],
    ): self {
        return new self($method, $path, $query, $body, $headers);
    }

    /**
     * @param array<string, string> $params
     */
    public function withParams(array $params): self
    {
        return new self(
            $this->method,
            $this->path,
            $this->query,
            $this->body,
            $this->headers,
            $params,
            $this->identity,
        );
    }

    public function withIdentity(Identity $identity): self
    {
        return new self(
            $this->method,
            $this->path,
            $this->query,
            $this->body,
            $this->headers,
            $this->params,
            $identity,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function body(): array
    {
        return $this->body;
    }

    public function param(string $name): ?string
    {
        return $this->params[$name] ?? null;
    }

    public function query(string $name): ?string
    {
        return $this->query[$name] ?? null;
    }

    /**
     * @return array<string, string>
     */
    public function queryParams(): array
    {
        return $this->query;
    }

    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }

    public function bearerToken(): ?string
    {
        $header = $this->header('authorization');

        if ($header === null || preg_match('/^Bearer\s+(\S+)$/i', $header, $matches) !== 1) {
            return null;
        }

        return $matches[1];
    }

    public function identity(): Identity
    {
        if ($this->identity === null) {
            throw new LogicException('route reached without RequireAuth');
        }

        return $this->identity;
    }

    /**
     * @return array<string, string>
     */
    private static function readQuery(): array
    {
        $query = [];

        foreach ($_GET as $key => $value) {
            if (is_string($key) && is_string($value)) {
                $query[$key] = $value;
            }
        }

        return $query;
    }

    /**
     * @return array<string, mixed>
     */
    private static function readBody(): array
    {
        $raw = file_get_contents('php://input');

        if ($raw === false || trim($raw) === '') {
            return [];
        }

        $decoded = json_decode($raw, true);

        if (!is_array($decoded) || ($decoded !== [] && array_is_list($decoded))) {
            throw HttpException::badRequest('invalid_json', 'corpo da requisição deve ser um objeto JSON');
        }

        return $decoded;
    }

    /**
     * @return array<string, string>
     */
    private static function readHeaders(): array
    {
        $headers = [];

        foreach ($_SERVER as $key => $value) {
            if (is_string($key) && is_string($value) && str_starts_with($key, 'HTTP_')) {
                $headers[strtolower(str_replace('_', '-', substr($key, 5)))] = $value;
            }
        }

        return $headers;
    }
}
