<?php

declare(strict_types=1);

namespace App\Infrastructure;

use App\Domain\DuplicateExternalIdException;
use App\Domain\Sale;
use App\Domain\SaleStatus;
use App\Domain\SaleSummary;
use App\Domain\WalletEntryType;
use PDO;
use PDOException;

final class SaleRepository
{
    private const COLUMNS = 'id, external_id, campaign_id, seller_id, product_id, quantity,'
        . ' unit_value, status, created_by_user_id, created_at, canceled_at';

    private const LIST_COLUMNS = 's.id, s.external_id, s.campaign_id, s.seller_id, s.product_id,'
        . ' s.quantity, s.unit_value, s.status, s.created_by_user_id, s.created_at, s.canceled_at';

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

    /**
     * @return SaleSummary[]
     */
    public function search(
        ?int $campaignId,
        ?int $sellerId,
        ?SaleStatus $status,
        int $limit,
        int $offset,
    ): array {
        [$where, $bindings] = self::filters($campaignId, $sellerId, $status);

        // Os pontos saem do lançamento de crédito, não de quantity * points_per_unit: o
        // produto pode ter sido editado depois da venda, e a listagem tem que mostrar o
        // que de fato entrou na carteira do vendedor.
        $statement = $this->database->pdo()->prepare(
            'SELECT ' . self::LIST_COLUMNS
            . ', (SELECT w.points FROM wallet_entries w WHERE w.sale_id = s.id AND w.type = :credit LIMIT 1) AS points'
            . ' FROM sales s' . $where
            . ' ORDER BY s.created_at DESC, s.id DESC LIMIT :limit OFFSET :offset'
        );

        $statement->bindValue(':credit', WalletEntryType::Credit->value);

        foreach ($bindings as $name => $value) {
            $statement->bindValue(':' . $name, $value);
        }

        $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
        $statement->bindValue(':offset', $offset, PDO::PARAM_INT);
        $statement->execute();

        return array_map(
            static fn (array $row): SaleSummary => new SaleSummary(
                self::hydrate($row),
                $row['points'] === null ? null : (int) $row['points'],
            ),
            $statement->fetchAll(),
        );
    }

    public function countMatching(?int $campaignId, ?int $sellerId, ?SaleStatus $status): int
    {
        [$where, $bindings] = self::filters($campaignId, $sellerId, $status);

        $statement = $this->database->pdo()->prepare('SELECT COUNT(*) FROM sales s' . $where);
        $statement->execute($bindings);

        return (int) $statement->fetchColumn();
    }

    /**
     * O SQL montado aqui só concatena trechos constantes escolhidos por filtro presente;
     * todo valor que veio do cliente entra por placeholder.
     *
     * @return array{string, array<string, int|string>}
     */
    private static function filters(?int $campaignId, ?int $sellerId, ?SaleStatus $status): array
    {
        $conditions = [];
        $bindings = [];

        if ($campaignId !== null) {
            $conditions[] = 's.campaign_id = :campaign_id';
            $bindings['campaign_id'] = $campaignId;
        }

        if ($sellerId !== null) {
            $conditions[] = 's.seller_id = :seller_id';
            $bindings['seller_id'] = $sellerId;
        }

        if ($status !== null) {
            $conditions[] = 's.status = :status';
            $bindings['status'] = $status->value;
        }

        return [$conditions === [] ? '' : ' WHERE ' . implode(' AND ', $conditions), $bindings];
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
