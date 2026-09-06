<?php

declare(strict_types=1);

namespace App\Infrastructure;

use App\Domain\Product;
use App\Support\HttpException;
use PDOException;

final class ProductRepository
{
    private const COLUMNS = 'id, name, sku, points_per_unit, active, created_at';

    public function __construct(private readonly Database $database)
    {
    }

    /**
     * @return Product[]
     */
    public function all(): array
    {
        $statement = $this->database->pdo()->query(
            'SELECT ' . self::COLUMNS . ' FROM products ORDER BY id'
        );

        return array_map(self::hydrate(...), $statement->fetchAll());
    }

    public function find(int $id): ?Product
    {
        $statement = $this->database->pdo()->prepare(
            'SELECT ' . self::COLUMNS . ' FROM products WHERE id = ?'
        );
        $statement->execute([$id]);
        $row = $statement->fetch();

        return $row === false ? null : self::hydrate($row);
    }

    public function create(string $name, string $sku, int $pointsPerUnit, bool $active): Product
    {
        $pdo = $this->database->pdo();
        $statement = $pdo->prepare(
            'INSERT INTO products (name, sku, points_per_unit, active) VALUES (?, ?, ?, ?)'
        );

        try {
            $statement->execute([$name, $sku, $pointsPerUnit, (int) $active]);
        } catch (PDOException $e) {
            throw self::translate($e);
        }

        $created = $this->find((int) $pdo->lastInsertId());

        if ($created === null) {
            throw HttpException::notFound();
        }

        return $created;
    }

    public function update(int $id, string $name, string $sku, int $pointsPerUnit, bool $active): ?Product
    {
        $statement = $this->database->pdo()->prepare(
            'UPDATE products SET name = ?, sku = ?, points_per_unit = ?, active = ? WHERE id = ?'
        );

        try {
            $statement->execute([$name, $sku, $pointsPerUnit, (int) $active, $id]);
        } catch (PDOException $e) {
            throw self::translate($e);
        }

        // rowCount() não serve para detectar ausência: o MySQL reporta 0 linhas afetadas
        // quando o UPDATE grava exatamente os valores que já estavam lá.
        return $this->find($id);
    }

    public function deactivate(int $id): ?Product
    {
        $statement = $this->database->pdo()->prepare('UPDATE products SET active = 0 WHERE id = ?');
        $statement->execute([$id]);

        return $this->find($id);
    }

    private static function translate(PDOException $e): PDOException|HttpException
    {
        return Database::isUniqueViolation($e)
            ? HttpException::unprocessable(['sku' => 'já existe um produto com este sku'])
            : $e;
    }

    /**
     * @param array<string, mixed> $row
     */
    private static function hydrate(array $row): Product
    {
        return new Product(
            (int) $row['id'],
            (string) $row['name'],
            (string) $row['sku'],
            (int) $row['points_per_unit'],
            (bool) $row['active'],
            (string) $row['created_at'],
        );
    }
}
