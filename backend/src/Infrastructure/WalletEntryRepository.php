<?php

declare(strict_types=1);

namespace App\Infrastructure;

use App\Domain\WalletEntryType;

final class WalletEntryRepository
{
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
}
