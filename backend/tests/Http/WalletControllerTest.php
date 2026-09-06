<?php

declare(strict_types=1);

namespace App\Tests\Http;

use App\Domain\Identity;
use App\Domain\Role;
use App\Domain\ScoringService;
use App\Domain\WalletEntryType;
use App\Http\Controllers\WalletController;
use App\Http\Request;
use App\Http\Response;
use App\Infrastructure\CampaignRepository;
use App\Infrastructure\ProductRepository;
use App\Infrastructure\SaleRepository;
use App\Infrastructure\UserRepository;
use App\Infrastructure\WalletEntryRepository;
use App\Support\HttpException;
use App\Tests\Support\IntegrationTestCase;

final class WalletControllerTest extends IntegrationTestCase
{
    private WalletController $controller;
    private ScoringService $scoring;
    private int $sellerId;
    private int $otherSellerId;
    private int $adminId;
    private int $campaignId;
    private int $productId;

    protected function setUp(): void
    {
        parent::setUp();

        $walletEntries = new WalletEntryRepository($this->database);

        $this->controller = new WalletController($walletEntries);
        $this->scoring = new ScoringService(
            $this->database,
            new CampaignRepository($this->database),
            new ProductRepository($this->database),
            new UserRepository($this->database),
            new SaleRepository($this->database),
            $walletEntries,
        );

        $this->sellerId = $this->createUser(Role::Seller);
        $this->otherSellerId = $this->createUser(Role::Seller);
        $this->adminId = $this->createUser(Role::Admin);
        $this->campaignId = $this->createCampaign(10000);
        $this->productId = $this->createProduct(10);
    }

    public function testWalletSumsLedgerBalanceAndListsNewestEntriesFirst(): void
    {
        $this->register('extrato-1', $this->sellerId, 1);
        $this->register('extrato-2', $this->sellerId, 2);
        $this->register('extrato-3', $this->sellerId, 3);

        $body = $this->wallet($this->sellerId)->body();

        $this->assertSame($this->sellerId, $body['seller_id']);
        $this->assertSame(60, $body['balance']);
        $this->assertSame(['limit' => 20, 'offset' => 0, 'total' => 3], $body['pagination']);
        $this->assertCount(3, $body['data']);
        $this->assertSame([30, 20, 10], array_column($body['data'], 'points'));
        $this->assertSame(WalletEntryType::Credit->value, $body['data'][0]['type']);
        $this->assertSame($this->sellerId, $body['data'][0]['seller_id']);
    }

    public function testStatementIsSlicedByLimitAndOffset(): void
    {
        $this->register('pagina-1', $this->sellerId, 1);
        $this->register('pagina-2', $this->sellerId, 2);
        $this->register('pagina-3', $this->sellerId, 3);

        $body = $this->wallet($this->sellerId, ['limit' => '2', 'offset' => '1'])->body();

        $this->assertSame(['limit' => 2, 'offset' => 1, 'total' => 3], $body['pagination']);
        $this->assertSame([20, 10], array_column($body['data'], 'points'));
        $this->assertSame(60, $body['balance']);
    }

    public function testOffsetBeyondTotalReturnsEmptyPageWithBalanceIntact(): void
    {
        $this->register('pagina-vazia', $this->sellerId, 1);

        $body = $this->wallet($this->sellerId, ['offset' => '10'])->body();

        $this->assertSame([], $body['data']);
        $this->assertSame(1, $body['pagination']['total']);
        $this->assertSame(10, $body['balance']);
    }

    public function testCancelShowsAsDebitAndLowersBalance(): void
    {
        $this->register('venda-estornada', $this->sellerId, 2);
        $this->scoring->cancelSale($this->externalId('venda-estornada'));

        $body = $this->wallet($this->sellerId)->body();

        $this->assertSame(0, $body['balance']);
        $this->assertSame(2, $body['pagination']['total']);
        $this->assertSame(WalletEntryType::Debit->value, $body['data'][0]['type']);
        $this->assertSame(20, $body['data'][0]['points']);
        $this->assertSame(WalletEntryType::Credit->value, $body['data'][1]['type']);
    }

    public function testWalletOnlySeesEntriesOfItsOwnSeller(): void
    {
        $this->register('minha-venda', $this->sellerId, 1);
        $this->register('venda-do-outro', $this->otherSellerId, 5);

        $body = $this->wallet($this->sellerId)->body();

        $this->assertSame(10, $body['balance']);
        $this->assertSame(1, $body['pagination']['total']);
        $this->assertSame([$this->sellerId], array_column($body['data'], 'seller_id'));
    }

    public function testAskingForAnotherSellerWalletIsNotFound(): void
    {
        $this->register('venda-do-outro', $this->otherSellerId, 1);

        try {
            $this->wallet($this->sellerId, ['seller_id' => (string) $this->otherSellerId]);
            $this->fail('a carteira de terceiro deveria responder 404');
        } catch (HttpException $e) {
            $this->assertSame(404, $e->status());
            $this->assertSame('not_found', $e->errorCode());
        }
    }

    public function testAskingForOwnWalletByIdStillWorks(): void
    {
        $this->register('venda-propria', $this->sellerId, 1);

        $body = $this->wallet($this->sellerId, ['seller_id' => (string) $this->sellerId])->body();

        $this->assertSame(10, $body['balance']);
    }

    public function testInvalidPaginationIsRejectedWithFieldMap(): void
    {
        try {
            $this->wallet($this->sellerId, ['limit' => '0', 'offset' => 'abc']);
            $this->fail('a paginação inválida deveria ter sido rejeitada');
        } catch (HttpException $e) {
            $this->assertSame(422, $e->status());
            $this->assertArrayHasKey('limit', $e->fields());
            $this->assertArrayHasKey('offset', $e->fields());
        }
    }

    public function testLimitAboveTheCapIsRejected(): void
    {
        try {
            $this->wallet($this->sellerId, ['limit' => '101']);
            $this->fail('o limit acima do teto deveria ter sido rejeitado');
        } catch (HttpException $e) {
            $this->assertSame(422, $e->status());
            $this->assertArrayHasKey('limit', $e->fields());
        }
    }

    /**
     * @param array<string, string> $query
     */
    private function wallet(int $sellerId, array $query = []): Response
    {
        $request = Request::create('GET', '/me/wallet', $query)
            ->withIdentity(new Identity($sellerId, Role::Seller));

        return $this->controller->show($request);
    }

    private function register(string $externalId, int $sellerId, int $quantity): void
    {
        $this->scoring->registerSale(
            $this->externalId($externalId),
            $this->campaignId,
            $sellerId,
            $this->productId,
            $quantity,
            '19.90',
            $this->adminId,
        );
    }
}
