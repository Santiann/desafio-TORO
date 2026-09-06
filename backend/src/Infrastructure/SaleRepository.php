<?php

declare(strict_types=1);

namespace App\Infrastructure;

use App\Domain\DuplicateExternalIdException;
use App\Domain\Sale;
use App\Domain\SaleStatus;
use PDOException;

final class SaleRepository
{
    private const COLUMNS = 'id, external_id, campaign_id, seller_id, product_id, quantity,'
        . ' unit_value, status, created_by_user_id, created_at, canceled_at';

    public function __construct(private readonly Database $database)
    {
    }

    public function find(int $id): ?Sale
    {
        $statement = $this->database->pdo()->prepare(
            'SELECT ' . self::COLUMNS . ' FROM sales WHERE id = ?'
        );
        $statement->execute([$id]);
        $row = $statement->fetch();

        return $row === false ? null : self::hydrate($row);
    }

    public function findByExternalId(string $externalId): ?Sale
    {
        $statement = $this->database->pdo()->prepare(
            'SELECT ' . self::COLUMNS . ' FROM sales WHERE external_id = ?'
        );
        $statement->execute([$externalId]);
        $row = $statement->fetch();

        return $row === false ? null : self::hydrate($row);
    }

    public function insert(
        string $externalId,
        int $campaignId,
        int $sellerId,
        int $productId,
        int $quantity,
        string $unitValue,
        int $createdByUserId,
    ): int {
        $pdo = $this->database->pdo();
        $statement = $pdo->prepare(
            'INSERT INTO sales (external_id, campaign_id, seller_id, product_id, quantity,'
            . ' unit_value, status, created_by_user_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );

        try {
            $statement->execute([
                $externalId,
                $campaignId,
                $sellerId,
                $productId,
                $quantity,
                $unitValue,
                SaleStatus::Approved->value,
                $createdByUserId,
            ]);
        } catch (PDOException $e) {
            // A violação do índice único é a própria checagem de duplicidade: um SELECT
            // antes do INSERT perderia a corrida entre dois lançamentos simultâneos.
            if (Database::isUniqueViolation($e)) {
                throw new DuplicateExternalIdException($externalId);
            }

            throw $e;
        }

        return (int) $pdo->lastInsertId();
    }

    public function markCanceled(string $externalId): bool
    {
        $statement = $this->database->pdo()->prepare(
            'UPDATE sales SET status = ?, canceled_at = NOW() WHERE external_id = ? AND status = ?'
        );
        $statement->execute([SaleStatus::Canceled->value, $externalId, SaleStatus::Approved->value]);

        return $statement->rowCount() === 1;
    }

    /**
     * @param array<string, mixed> $row
     */
    private static function hydrate(array $row): Sale
    {
        return new Sale(
            (int) $row['id'],
            (string) $row['external_id'],
            (int) $row['campaign_id'],
            (int) $row['seller_id'],
            (int) $row['product_id'],
            (int) $row['quantity'],
            (string) $row['unit_value'],
            SaleStatus::from((string) $row['status']),
            (int) $row['created_by_user_id'],
            (string) $row['created_at'],
            $row['canceled_at'] === null ? null : (string) $row['canceled_at'],
        );
    }
}
