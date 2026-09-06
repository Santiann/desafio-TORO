<?php

declare(strict_types=1);

namespace App\Infrastructure;

use App\Support\Env;
use PDO;
use PDOException;

final class Database
{
    private ?PDO $pdo = null;

    public function __construct(
        private readonly string $host,
        private readonly int $port,
        private readonly string $database,
        private readonly string $username,
        private readonly string $password,
    ) {
    }

    public static function fromEnv(): self
    {
        return new self(
            Env::required('DB_HOST'),
            Env::int('DB_PORT', 3306),
            Env::required('DB_DATABASE'),
            Env::required('DB_USERNAME'),
            Env::required('DB_PASSWORD'),
        );
    }

    public static function isUniqueViolation(PDOException $e): bool
    {
        return $e->getCode() === '23000' && ($e->errorInfo[1] ?? null) === 1062;
    }

    public function pdo(): PDO
    {
        return $this->pdo ??= new PDO($this->dsn(), $this->username, $this->password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_STRINGIFY_FETCHES => false,
        ]);
    }

    public function connectWithRetry(int $attempts, int $delaySeconds): PDO
    {
        for ($attempt = 1; ; $attempt++) {
            try {
                return $this->pdo();
            } catch (PDOException $e) {
                if ($attempt >= $attempts) {
                    throw $e;
                }

                sleep($delaySeconds);
            }
        }
    }

    private function dsn(): string
    {
        return sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            $this->host,
            $this->port,
            $this->database,
        );
    }
}
