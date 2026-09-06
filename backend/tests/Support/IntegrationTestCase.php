<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Domain\CampaignStatus;
use App\Domain\Role;
use App\Domain\WalletEntryType;
use App\Infrastructure\Database;
use DateTimeImmutable;
use PDO;
use PHPUnit\Framework\TestCase;

abstract class IntegrationTestCase extends TestCase
{
    protected Database $database;
    protected PDO $pdo;
    protected string $run;

    /**
     * @var int[]
     */
    private array $campaignIds = [];

    /**
     * @var int[]
     */
    private array $productIds = [];

    /**
     * @var int[]
     */
    private array $userIds = [];

    protected function setUp(): void
    {
        $this->database = Database::fromEnv();
        $this->pdo = $this->database->pdo();
        $this->run = self::token();
    }

    protected function tearDown(): void
    {
        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }

        $deleteEntries = $this->pdo->prepare('DELETE FROM wallet_entries WHERE campaign_id = ?');
        $deleteSales = $this->pdo->prepare('DELETE FROM sales WHERE campaign_id = ?');
        $deleteCampaign = $this->pdo->prepare('DELETE FROM campaigns WHERE id = ?');
        $deleteProduct = $this->pdo->prepare('DELETE FROM products WHERE id = ?');
        $deleteUser = $this->pdo->prepare('DELETE FROM users WHERE id = ?');

        foreach ($this->campaignIds as $id) {
            $deleteEntries->execute([$id]);
            $deleteSales->execute([$id]);
            $deleteCampaign->execute([$id]);
        }

        foreach ($this->productIds as $id) {
            $deleteProduct->execute([$id]);
        }

        foreach ($this->userIds as $id) {
            $deleteUser->execute([$id]);
        }

        $this->campaignIds = [];
        $this->productIds = [];
        $this->userIds = [];
    }

    protected function createCampaign(
        int $budgetTotal,
        CampaignStatus $status = CampaignStatus::Active,
        string $startsAt = '-1 day',
        string $endsAt = '+30 days',
    ): int {
        $now = new DateTimeImmutable();
        $statement = $this->pdo->prepare(
            'INSERT INTO campaigns (name, budget_total, starts_at, ends_at, status) VALUES (?, ?, ?, ?, ?)'
        );
        $statement->execute([
            'Campanha ' . self::token(),
            $budgetTotal,
            $now->modify($startsAt)->format('Y-m-d H:i:s'),
            $now->modify($endsAt)->format('Y-m-d H:i:s'),
            $status->value,
        ]);

        $id = (int) $this->pdo->lastInsertId();
        $this->campaignIds[] = $id;

        return $id;
    }

    protected function createProduct(int $pointsPerUnit, bool $active = true): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO products (name, sku, points_per_unit, active) VALUES (?, ?, ?, ?)'
        );
        $statement->execute(['Produto de teste', 'TEST-' . self::token(), $pointsPerUnit, (int) $active]);

        $id = (int) $this->pdo->lastInsertId();
        $this->productIds[] = $id;

        return $id;
    }

    protected function createUser(Role $role): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, ?)'
        );
        $statement->execute([
            'Usuario de teste',
            'test-' . self::token() . '@toro.test',
            password_hash(self::token(), PASSWORD_DEFAULT),
            $role->value,
        ]);

        $id = (int) $this->pdo->lastInsertId();
        $this->userIds[] = $id;

        return $id;
    }

    protected function budgetUsed(int $campaignId): int
    {
        $statement = $this->pdo->prepare('SELECT budget_used FROM campaigns WHERE id = ?');
        $statement->execute([$campaignId]);

        return (int) $statement->fetchColumn();
    }

    protected function countEntries(int $campaignId, WalletEntryType $type): int
    {
        $statement = $this->pdo->prepare(
            'SELECT COUNT(*) FROM wallet_entries WHERE campaign_id = ? AND type = ?'
        );
        $statement->execute([$campaignId, $type->value]);

        return (int) $statement->fetchColumn();
    }

    protected function ledgerBalance(int $campaignId): int
    {
        $statement = $this->pdo->prepare(
            'SELECT COALESCE(SUM(CASE WHEN type = ? THEN points ELSE -points END), 0)'
            . ' FROM wallet_entries WHERE campaign_id = ?'
        );
        $statement->execute([WalletEntryType::Credit->value, $campaignId]);

        return (int) $statement->fetchColumn();
    }

    protected function countSales(int $campaignId): int
    {
        $statement = $this->pdo->prepare('SELECT COUNT(*) FROM sales WHERE campaign_id = ?');
        $statement->execute([$campaignId]);

        return (int) $statement->fetchColumn();
    }

    protected function setProductPoints(int $productId, int $pointsPerUnit): void
    {
        $statement = $this->pdo->prepare('UPDATE products SET points_per_unit = ? WHERE id = ?');
        $statement->execute([$pointsPerUnit, $productId]);
    }

    protected function externalId(string $name): string
    {
        return $name . '-' . $this->run;
    }

    protected static function token(): string
    {
        return bin2hex(random_bytes(8));
    }
}
