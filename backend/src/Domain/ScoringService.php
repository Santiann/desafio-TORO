<?php

declare(strict_types=1);

namespace App\Domain;

use App\Infrastructure\CampaignRepository;
use App\Infrastructure\Database;
use App\Infrastructure\ProductRepository;
use App\Infrastructure\SaleRepository;
use App\Infrastructure\UserRepository;
use App\Infrastructure\WalletEntryRepository;
use DateTimeImmutable;
use RuntimeException;
use Throwable;

final class ScoringService
{
    public function __construct(
        private readonly Database $database,
        private readonly CampaignRepository $campaigns,
        private readonly ProductRepository $products,
        private readonly UserRepository $users,
        private readonly SaleRepository $sales,
        private readonly WalletEntryRepository $walletEntries,
    ) {
    }

    public function registerSale(
        string $externalId,
        int $campaignId,
        int $sellerId,
        int $productId,
        int $quantity,
        string $unitValue,
        int $createdByUserId,
    ): SaleOutcome {
        $this->database->begin();

        try {
            // A campanha é sempre a primeira linha travada, tanto aqui quanto no
            // cancelamento: ordem de lock fixa é o que impede deadlock entre os dois.
            $campaign = $this->lockOpenCampaign($campaignId);
            $product = $this->activeProduct($productId);
            $this->assertSellerExists($sellerId);

            if ($quantity <= 0) {
                throw ScoringException::invalid(['quantity' => 'deve ser maior que zero']);
            }

            $points = $quantity * $product->pointsPerUnit;
            $available = $campaign->budgetTotal - $campaign->budgetUsed;

            if ($points > $available) {
                throw ScoringException::budgetExceeded($available, $points);
            }

            $saleId = $this->sales->insert(
                $externalId,
                $campaignId,
                $sellerId,
                $productId,
                $quantity,
                $unitValue,
                $createdByUserId,
            );

            $this->walletEntries->record(
                WalletEntryType::Credit,
                $sellerId,
                $campaignId,
                $saleId,
                $points,
                "crédito da venda {$externalId}",
            );

            if (!$this->campaigns->addUsage($campaignId, $points)) {
                throw ScoringException::budgetExceeded($available, $points);
            }

            $sale = $this->reload($saleId);

            $this->database->commit();

            return new SaleOutcome($sale, true);
        } catch (DuplicateExternalIdException) {
            $this->database->rollback();

            return new SaleOutcome($this->reloadByExternalId($externalId), false);
        } catch (Throwable $e) {
            $this->database->rollback();

            throw $e;
        }
    }

    public function cancelSale(string $externalId): ?SaleOutcome
    {
        $this->database->begin();

        try {
            $sale = $this->sales->findByExternalId($externalId);

            if ($sale === null) {
                $this->database->rollback();

                return null;
            }

            $this->campaigns->lockForUpdate($sale->campaignId);

            if (!$this->sales->markCanceled($externalId)) {
                $this->database->rollback();

                return new SaleOutcome($this->reloadByExternalId($externalId), false);
            }

            // Os pontos saem do lançamento de crédito, nunca de points_per_unit: o produto
            // pode ter sido editado depois da venda e recalcular corromperia o budget_used.
            $points = $this->walletEntries->creditPoints($sale->id);

            if ($points === null) {
                throw new RuntimeException("approved sale without credit entry: {$externalId}");
            }

            $this->walletEntries->record(
                WalletEntryType::Debit,
                $sale->sellerId,
                $sale->campaignId,
                $sale->id,
                $points,
                "estorno da venda {$externalId}",
            );

            if (!$this->campaigns->releaseUsage($sale->campaignId, $points)) {
                throw new RuntimeException("budget release rejected for campaign {$sale->campaignId}");
            }

            $canceled = $this->reload($sale->id);

            $this->database->commit();

            return new SaleOutcome($canceled, true);
        } catch (Throwable $e) {
            $this->database->rollback();

            throw $e;
        }
    }

    private function lockOpenCampaign(int $campaignId): Campaign
    {
        $campaign = $this->campaigns->lockForUpdate($campaignId);

        if ($campaign === null) {
            throw ScoringException::invalid(['campaign_id' => 'campanha não encontrada']);
        }

        if ($campaign->status !== CampaignStatus::Active) {
            throw ScoringException::invalid(['campaign_id' => 'campanha não está ativa']);
        }

        $now = new DateTimeImmutable();

        if ($now < new DateTimeImmutable($campaign->startsAt) || $now > new DateTimeImmutable($campaign->endsAt)) {
            throw ScoringException::invalid(['campaign_id' => 'campanha fora do período de vigência']);
        }

        return $campaign;
    }

    private function activeProduct(int $productId): Product
    {
        $product = $this->products->find($productId);

        if ($product === null) {
            throw ScoringException::invalid(['product_id' => 'produto não encontrado']);
        }

        if (!$product->active) {
            throw ScoringException::invalid(['product_id' => 'produto inativo']);
        }

        return $product;
    }

    private function assertSellerExists(int $sellerId): void
    {
        $seller = $this->users->find($sellerId);

        if ($seller === null || $seller->role !== Role::Seller) {
            throw ScoringException::invalid(['seller_id' => 'vendedor não encontrado']);
        }
    }

    private function reload(int $saleId): Sale
    {
        $sale = $this->sales->find($saleId);

        if ($sale === null) {
            throw new RuntimeException("sale disappeared after write: {$saleId}");
        }

        return $sale;
    }

    private function reloadByExternalId(string $externalId): Sale
    {
        $sale = $this->sales->findByExternalId($externalId);

        if ($sale === null) {
            throw new RuntimeException("sale disappeared after rollback: {$externalId}");
        }

        return $sale;
    }
}
