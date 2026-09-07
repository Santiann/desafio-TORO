<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure;

use App\Domain\Role;
use App\Domain\SaleStatus;
use App\Domain\ScoringService;
use App\Infrastructure\CampaignRepository;
use App\Infrastructure\ProductRepository;
use App\Infrastructure\SaleRepository;
use App\Infrastructure\UserRepository;
use App\Infrastructure\WalletEntryRepository;
use App\Tests\Support\IntegrationTestCase;

final class SaleRepositoryTest extends IntegrationTestCase
{
    private ScoringService $scoring;
    private SaleRepository $sales;
    private int $campaignId;
    private int $productId;
    private int $anaId;
    private int $brunoId;
    private int $adminId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sales = new SaleRepository($this->database);
        $this->scoring = new ScoringService(
            $this->database,
            new CampaignRepository($this->database),
            new ProductRepository($this->database),
            new UserRepository($this->database),
            $this->sales,
            new WalletEntryRepository($this->database),
        );

        $this->campaignId = $this->createCampaign(1000);
        $this->productId = $this->createProduct(10);
        $this->anaId = $this->createUser(Role::Seller);
        $this->brunoId = $this->createUser(Role::Seller);
        $this->adminId = $this->createUser(Role::Admin);

        $this->register('listagem-ana-1', $this->anaId, 1);
        $this->register('listagem-ana-2', $this->anaId, 2);
        $this->register('listagem-bruno-1', $this->brunoId, 3);
        $this->scoring->cancelSale($this->externalId('listagem-bruno-1'));
    }

    public function testFiltersNarrowTheListAndTheCount(): void
    {
        $this->assertSame(3, $this->sales->countMatching($this->campaignId, null, null));
        $this->assertSame(2, $this->sales->countMatching($this->campaignId, $this->anaId, null));
        $this->assertSame(1, $this->sales->countMatching($this->campaignId, null, SaleStatus::Canceled));
        $this->assertSame(0, $this->sales->countMatching($this->campaignId, $this->anaId, SaleStatus::Canceled));

        $canceled = $this->sales->search($this->campaignId, null, SaleStatus::Canceled, 20, 0);

        $this->assertCount(1, $canceled);
        $this->assertSame($this->externalId('listagem-bruno-1'), $canceled[0]->sale->externalId);
    }

    public function testListingIsNewestFirstAndSlicedByLimitAndOffset(): void
    {
        $page = $this->sales->search($this->campaignId, null, null, 2, 0);

        $this->assertCount(2, $page);
        $this->assertSame($this->externalId('listagem-bruno-1'), $page[0]->sale->externalId);

        $this->assertCount(1, $this->sales->search($this->campaignId, null, null, 2, 2));
        $this->assertCount(0, $this->sales->search($this->campaignId, null, null, 2, 9));
    }

    public function testPointsComeFromTheLedgerAndNotFromTheCurrentProductValue(): void
    {
        $this->setProductPoints($this->productId, 999);

        $sales = $this->sales->search($this->campaignId, $this->anaId, null, 20, 0);
        $points = array_map(static fn ($summary): ?int => $summary->points, $sales);

        sort($points);

        $this->assertSame([10, 20], $points);
    }

    private function register(string $externalId, int $sellerId, int $quantity): void
    {
        $this->scoring->registerSale(
            $this->externalId($externalId),
            $this->campaignId,
            $sellerId,
            $this->productId,
            $quantity,
            '100.00',
            $this->adminId,
        );
    }
}
