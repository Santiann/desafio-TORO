<?php

declare(strict_types=1);

namespace App\Infrastructure;

use App\Domain\WalletEntry;
use App\Domain\WalletEntryType;
use PDO;

final class WalletEntryRepository
{
    private const COLUMNS = 'id, seller_id, campaign_id, sale_id, type, points, description, created_at';

    public function __construct(private readonly Database $database)
    {
    }

    public function record(
        WalletEntryType $type,
        int $sellerId,
        int $campaignId,
        int $saleId,
        int $points,
        string $description,
    ): void {
        $statement = $this->database->pdo()->prepare(
            'INSERT INTO wallet_entries (seller_id, campaign_id, sale_id, type, points, description)'
            . ' VALUES (?, ?, ?, ?, ?, ?)'
        );
        $statement->execute([$sellerId, $campaignId, $saleId, $type->value, $points, $description]);
    }

    public function creditPoints(int $saleId): ?int
    {
        $statement = $this->database->pdo()->prepare(
            'SELECT points FROM wallet_entries WHERE sale_id = ? AND type = ? LIMIT 1'
        );
        $statement->execute([$saleId, WalletEntryType::Credit->value]);
        $points = $statement->fetchColumn();

        return $points === false ? null : (int) $points;
    }

    public function balance(int $sellerId): int
    {
        $statement = $this->database->pdo()->prepare(
            'SELECT COALESCE(SUM(CASE WHEN type = ? THEN points ELSE -points END), 0)'
            . ' FROM wallet_entries WHERE seller_id = ?'
        );
        $statement->execute([WalletEntryType::Credit->value, $sellerId]);

        return (int) $statement->fetchColumn();
    }

    public function countBySeller(int $sellerId): int
    {
        $statement = $this->database->pdo()->prepare(
            'SELECT COUNT(*) FROM wallet_entries WHERE seller_id = ?'
        );
        $statement->execute([$sellerId]);

        return (int) $statement->fetchColumn();
    }

    /**
     * @return WalletEntry[]
     */
    public function listBySeller(int $sellerId, int $limit, int $offset): array
    {
        $statement = $this->database->pdo()->prepare(
            'SELECT ' . self::COLUMNS . ' FROM wallet_entries WHERE seller_id = :seller_id'
            . ' ORDER BY created_at DESC, id DESC LIMIT :limit OFFSET :offset'
        );
        $statement->bindValue(':seller_id', $sellerId, PDO::PARAM_INT);
        $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
        $statement->bindValue(':offset', $offset, PDO::PARAM_INT);
        $statement->execute();

        return array_map(self::hydrate(...), $statement->fetchAll());
    }

    /**
     * @param array<string, mixed> $row
     */
    private static function hydrate(array $row): WalletEntry
    {
        return new WalletEntry(
            (int) $row['id'],
            (int) $row['seller_id'],
            (int) $row['campaign_id'],
            (int) $row['sale_id'],
            WalletEntryType::from((string) $row['type']),
            (int) $row['points'],
            (string) $row['description'],
            (string) $row['created_at'],
        );
    }
}
