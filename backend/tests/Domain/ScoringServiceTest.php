<?php

declare(strict_types=1);

namespace App\Tests\Domain;

use App\Domain\CampaignStatus;
use App\Domain\Role;
use App\Domain\SaleOutcome;
use App\Domain\SaleStatus;
use App\Domain\ScoringException;
use App\Domain\ScoringService;
use App\Domain\WalletEntryType;
use App\Infrastructure\CampaignRepository;
use App\Infrastructure\ProductRepository;
use App\Infrastructure\SaleRepository;
use App\Infrastructure\UserRepository;
use App\Infrastructure\WalletEntryRepository;
use App\Tests\Support\IntegrationTestCase;

final class ScoringServiceTest extends IntegrationTestCase
{
    private ScoringService $scoring;
    private int $sellerId;
    private int $adminId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->scoring = new ScoringService(
            $this->database,
            new CampaignRepository($this->database),
            new ProductRepository($this->database),
            new UserRepository($this->database),
            new SaleRepository($this->database),
            new WalletEntryRepository($this->database),
        );

        $this->sellerId = $this->createUser(Role::Seller);
        $this->adminId = $this->createUser(Role::Admin);
    }

    public function testRegisterSaleCreditsWalletAndConsumesBudget(): void
    {
        $campaignId = $this->createCampaign(1000);
        $productId = $this->createProduct(10);

        $outcome = $this->register('venda-normal', $campaignId, $productId, 3);

        $this->assertTrue($outcome->applied);
        $this->assertSame(SaleStatus::Approved, $outcome->sale->status);
        $this->assertSame(3, $outcome->sale->quantity);
        $this->assertNull($outcome->sale->canceledAt);
        $this->assertSame(30, $this->budgetUsed($campaignId));
        $this->assertSame(30, $this->ledgerBalance($campaignId));
        $this->assertSame(1, $this->countEntries($campaignId, WalletEntryType::Credit));
        $this->assertSame(0, $this->countEntries($campaignId, WalletEntryType::Debit));
    }

    public function testSaleThatExceedsBudgetIsRejectedWhole(): void
    {
        $campaignId = $this->createCampaign(100);
        $productId = $this->createProduct(60);

        try {
            $this->register('venda-estouro', $campaignId, $productId, 2);
            $this->fail('a venda deveria ter sido rejeitada por falta de verba');
        } catch (ScoringException $e) {
            $this->assertSame('budget_exceeded', $e->errorCode);
        }

        $this->assertSame(0, $this->countSales($campaignId));
        $this->assertSame(0, $this->countEntries($campaignId, WalletEntryType::Credit));
        $this->assertSame(0, $this->budgetUsed($campaignId));
    }

    public function testSameExternalIdIsCreditedOnce(): void
    {
        $campaignId = $this->createCampaign(1000);
        $productId = $this->createProduct(10);

        $first = $this->register('venda-repetida', $campaignId, $productId, 3);
        $second = $this->register('venda-repetida', $campaignId, $productId, 3);

        $this->assertTrue($first->applied);
        $this->assertFalse($second->applied);
        $this->assertSame($first->sale->id, $second->sale->id);
        $this->assertSame(1, $this->countSales($campaignId));
        $this->assertSame(1, $this->countEntries($campaignId, WalletEntryType::Credit));
        $this->assertSame(30, $this->budgetUsed($campaignId));
        $this->assertSame(30, $this->ledgerBalance($campaignId));
    }

    public function testCancelDebitsWalletAndReturnsBudget(): void
    {
        $campaignId = $this->createCampaign(1000);
        $productId = $this->createProduct(10);
        $this->register('venda-cancelada', $campaignId, $productId, 3);

        $outcome = $this->scoring->cancelSale($this->externalId('venda-cancelada'));

        $this->assertInstanceOf(SaleOutcome::class, $outcome);
        $this->assertTrue($outcome->applied);
        $this->assertSame(SaleStatus::Canceled, $outcome->sale->status);
        $this->assertNotNull($outcome->sale->canceledAt);
        $this->assertSame(1, $this->countEntries($campaignId, WalletEntryType::Debit));
        $this->assertSame(0, $this->budgetUsed($campaignId));
        $this->assertSame(0, $this->ledgerBalance($campaignId));
    }

    public function testCancelTwiceDebitsOnce(): void
    {
        $campaignId = $this->createCampaign(1000);
        $productId = $this->createProduct(10);
        $this->register('venda-cancelada-2x', $campaignId, $productId, 3);

        $first = $this->scoring->cancelSale($this->externalId('venda-cancelada-2x'));
        $second = $this->scoring->cancelSale($this->externalId('venda-cancelada-2x'));

        $this->assertInstanceOf(SaleOutcome::class, $first);
        $this->assertInstanceOf(SaleOutcome::class, $second);
        $this->assertTrue($first->applied);
        $this->assertFalse($second->applied);
        $this->assertSame(SaleStatus::Canceled, $second->sale->status);
        $this->assertSame(1, $this->countEntries($campaignId, WalletEntryType::Debit));
        $this->assertSame(0, $this->budgetUsed($campaignId));
    }

    public function testCancelUnknownSaleHasNoEffect(): void
    {
        $campaignId = $this->createCampaign(1000);
        $productId = $this->createProduct(10);
        $this->register('venda-intacta', $campaignId, $productId, 3);

        $this->assertNull($this->scoring->cancelSale($this->externalId('venda-que-nao-existe')));
        $this->assertSame(1, $this->countSales($campaignId));
        $this->assertSame(0, $this->countEntries($campaignId, WalletEntryType::Debit));
        $this->assertSame(30, $this->budgetUsed($campaignId));
    }

    public function testBudgetReturnedByCancelIsReusable(): void
    {
        $campaignId = $this->createCampaign(100);
        $productId = $this->createProduct(50);

        $this->register('venda-cheia', $campaignId, $productId, 2);
        $this->assertSame(100, $this->budgetUsed($campaignId));

        try {
            $this->register('venda-sem-verba', $campaignId, $productId, 1);
            $this->fail('a segunda venda deveria ter sido rejeitada por falta de verba');
        } catch (ScoringException $e) {
            $this->assertSame('budget_exceeded', $e->errorCode);
        }

        $this->scoring->cancelSale($this->externalId('venda-cheia'));
        $this->assertSame(0, $this->budgetUsed($campaignId));

        $outcome = $this->register('venda-com-verba-devolvida', $campaignId, $productId, 1);

        $this->assertTrue($outcome->applied);
        $this->assertSame(50, $this->budgetUsed($campaignId));
        $this->assertSame(50, $this->ledgerBalance($campaignId));
    }

    public function testCampaignOutsideItsWindowRejectsSale(): void
    {
        $campaignId = $this->createCampaign(1000, CampaignStatus::Active, '-30 days', '-1 day');
        $productId = $this->createProduct(10);

        try {
            $this->register('venda-fora-da-vigencia', $campaignId, $productId, 1);
            $this->fail('a venda deveria ter sido rejeitada pela vigência da campanha');
        } catch (ScoringException $e) {
            $this->assertArrayHasKey('campaign_id', $e->fields);
        }

        $this->assertSame(0, $this->countSales($campaignId));
        $this->assertSame(0, $this->budgetUsed($campaignId));
    }

    public function testInactiveProductRejectsSale(): void
    {
        $campaignId = $this->createCampaign(1000);
        $productId = $this->createProduct(10, active: false);

        try {
            $this->register('venda-produto-inativo', $campaignId, $productId, 1);
            $this->fail('a venda deveria ter sido rejeitada pelo produto inativo');
        } catch (ScoringException $e) {
            $this->assertArrayHasKey('product_id', $e->fields);
        }

        $this->assertSame(0, $this->countSales($campaignId));
        $this->assertSame(0, $this->budgetUsed($campaignId));
    }

    public function testCancelUsesLedgerPointsAndNotTheCurrentProductValue(): void
    {
        $campaignId = $this->createCampaign(1000);
        $productId = $this->createProduct(10);
        $this->register('venda-produto-editado', $campaignId, $productId, 3);

        $this->setProductPoints($productId, 999);
        $this->scoring->cancelSale($this->externalId('venda-produto-editado'));

        $this->assertSame(0, $this->budgetUsed($campaignId));
        $this->assertSame(0, $this->ledgerBalance($campaignId));
    }

    public function testProductWorthZeroPointsSellsAndCancelsWithoutMovingBudget(): void
    {
        $campaignId = $this->createCampaign(1000);
        $productId = $this->createProduct(0);

        $outcome = $this->register('venda-brinde', $campaignId, $productId, 5);

        $this->assertTrue($outcome->applied);
        $this->assertSame(0, $this->budgetUsed($campaignId));
        $this->assertSame(1, $this->countEntries($campaignId, WalletEntryType::Credit));

        $canceled = $this->scoring->cancelSale($this->externalId('venda-brinde'));

        $this->assertNotNull($canceled);
        $this->assertTrue($canceled->applied);
        $this->assertSame(SaleStatus::Canceled, $canceled->sale->status);
        $this->assertSame(0, $this->budgetUsed($campaignId));
        $this->assertSame(1, $this->countEntries($campaignId, WalletEntryType::Debit));
    }

    public function testCancelStillWorksAfterTheCampaignIsClosed(): void
    {
        $campaignId = $this->createCampaign(1000);
        $productId = $this->createProduct(10);
        $this->register('venda-campanha-fechada', $campaignId, $productId, 3);

        $this->closeCampaign($campaignId);

        $canceled = $this->scoring->cancelSale($this->externalId('venda-campanha-fechada'));

        $this->assertNotNull($canceled);
        $this->assertTrue($canceled->applied);
        $this->assertSame(0, $this->budgetUsed($campaignId));
        $this->assertSame(0, $this->ledgerBalance($campaignId));
        $this->assertSame(1, $this->countEntries($campaignId, WalletEntryType::Debit));
    }

    private function register(string $externalId, int $campaignId, int $productId, int $quantity): SaleOutcome
    {
        return $this->scoring->registerSale(
            $this->externalId($externalId),
            $campaignId,
            $this->sellerId,
            $productId,
            $quantity,
            '19.90',
            $this->adminId,
        );
    }
}
