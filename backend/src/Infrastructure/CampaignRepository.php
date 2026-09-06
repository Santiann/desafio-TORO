<?php

declare(strict_types=1);

namespace App\Infrastructure;

use App\Domain\Campaign;
use App\Domain\CampaignStatus;
use App\Support\HttpException;

final class CampaignRepository
{
    private const COLUMNS = 'id, name, budget_total, budget_used, starts_at, ends_at, status, created_at';

    public function __construct(private readonly Database $database)
    {
    }

    /**
     * @return Campaign[]
     */
    public function all(): array
    {
        $statement = $this->database->pdo()->query(
            'SELECT ' . self::COLUMNS . ' FROM campaigns ORDER BY id'
        );

        return array_map(self::hydrate(...), $statement->fetchAll());
    }

    public function create(string $name, int $budgetTotal, string $startsAt, string $endsAt): Campaign
    {
        $pdo = $this->database->pdo();
        $statement = $pdo->prepare(
            'INSERT INTO campaigns (name, budget_total, starts_at, ends_at) VALUES (?, ?, ?, ?)'
        );
        $statement->execute([$name, $budgetTotal, $startsAt, $endsAt]);

        $created = $this->findById((int) $pdo->lastInsertId());

        if ($created === null) {
            throw HttpException::notFound();
        }

        return $created;
    }

    private function findById(int $id): ?Campaign
    {
        $statement = $this->database->pdo()->prepare(
            'SELECT ' . self::COLUMNS . ' FROM campaigns WHERE id = ?'
        );
        $statement->execute([$id]);
        $row = $statement->fetch();

        return $row === false ? null : self::hydrate($row);
    }

    /**
     * @param array<string, mixed> $row
     */
    private static function hydrate(array $row): Campaign
    {
        return new Campaign(
            (int) $row['id'],
            (string) $row['name'],
            (int) $row['budget_total'],
            (int) $row['budget_used'],
            (string) $row['starts_at'],
            (string) $row['ends_at'],
            CampaignStatus::from((string) $row['status']),
            (string) $row['created_at'],
        );
    }
}
