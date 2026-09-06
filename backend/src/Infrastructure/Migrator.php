<?php

declare(strict_types=1);

namespace App\Infrastructure;

use PDO;
use RuntimeException;

final class Migrator
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly string $directory,
    ) {
    }

    /**
     * @return string[]
     */
    public function run(): array
    {
        $this->createRegistry();

        $applied = $this->applied();
        $executed = [];

        foreach ($this->files() as $name => $path) {
            if (in_array($name, $applied, true)) {
                continue;
            }

            $sql = file_get_contents($path);

            if ($sql === false) {
                throw new RuntimeException("unreadable migration: {$name}");
            }

            $this->pdo->exec($sql);

            $statement = $this->pdo->prepare('INSERT INTO migrations (name) VALUES (?)');
            $statement->execute([$name]);

            $executed[] = $name;
        }

        return $executed;
    }

    private function createRegistry(): void
    {
        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS migrations ('
            . 'id INT UNSIGNED NOT NULL AUTO_INCREMENT,'
            . 'name VARCHAR(190) NOT NULL,'
            . 'applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,'
            . 'PRIMARY KEY (id),'
            . 'UNIQUE KEY uq_migrations_name (name)'
            . ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    /**
     * @return string[]
     */
    private function applied(): array
    {
        return $this->pdo->query('SELECT name FROM migrations')->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * @return array<string, string>
     */
    private function files(): array
    {
        $paths = glob($this->directory . '/*.sql');

        if ($paths === false) {
            throw new RuntimeException("unreadable migrations directory: {$this->directory}");
        }

        sort($paths, SORT_STRING);

        $files = [];

        foreach ($paths as $path) {
            $files[basename($path)] = $path;
        }

        return $files;
    }
}
