<?php

declare(strict_types=1);

use App\Domain\SaleStatus;
use App\Domain\ScoringService;
use App\Infrastructure\CampaignRepository;
use App\Infrastructure\Database;
use App\Infrastructure\ProductRepository;
use App\Infrastructure\SaleRepository;
use App\Infrastructure\UserRepository;
use App\Infrastructure\WalletEntryRepository;
use App\Support\Env;

require dirname(__DIR__) . '/vendor/autoload.php';

Env::load(dirname(__DIR__));

$database = Database::fromEnv();
$pdo = $database->connectWithRetry(30, 2);
$passwordHash = password_hash(Env::get('SEED_PASSWORD', 'password123'), PASSWORD_DEFAULT);

$users = [
    ['Admin Toro', 'admin@toro.test', 'admin'],
    ['Ana Souza', 'ana@toro.test', 'seller'],
    ['Bruno Lima', 'bruno@toro.test', 'seller'],
    ['Carla Dias', 'carla@toro.test', 'seller'],
];

$products = [
    ['CDB Prefixado', 'CDB-PRE', 10],
    ['CDB Pos-fixado', 'CDB-POS', 15],
    ['Tesouro Selic', 'TD-SELIC', 5],
    ['Fundo Multimercado', 'FUNDO-MM', 25],
];

$campaign = ['Campanha de Lancamento', 10000];

$findUser = $pdo->prepare('SELECT id FROM users WHERE email = ?');
$insertUser = $pdo->prepare(
    'INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, ?)'
);

$createdUsers = 0;

foreach ($users as [$name, $email, $role]) {
    $findUser->execute([$email]);

    if ($findUser->fetchColumn() !== false) {
        continue;
    }

    $insertUser->execute([$name, $email, $passwordHash, $role]);
    $createdUsers++;
}

$findProduct = $pdo->prepare('SELECT id FROM products WHERE sku = ?');
$insertProduct = $pdo->prepare(
    'INSERT INTO products (name, sku, points_per_unit, active) VALUES (?, ?, ?, 1)'
);

$createdProducts = 0;

foreach ($products as [$name, $sku, $pointsPerUnit]) {
    $findProduct->execute([$sku]);

    if ($findProduct->fetchColumn() !== false) {
        continue;
    }

    $insertProduct->execute([$name, $sku, $pointsPerUnit]);
    $createdProducts++;
}

$findCampaign = $pdo->prepare('SELECT id FROM campaigns WHERE name = ?');
$findCampaign->execute([$campaign[0]]);

$createdCampaigns = 0;

if ($findCampaign->fetchColumn() === false) {
    $insertCampaign = $pdo->prepare(
        'INSERT INTO campaigns (name, budget_total, starts_at, ends_at, status)'
        . ' VALUES (?, ?, NOW() - INTERVAL 1 DAY, NOW() + INTERVAL 89 DAY, ?)'
    );
    $insertCampaign->execute([$campaign[0], $campaign[1], 'active']);
    $createdCampaigns++;
}

// As vendas de exemplo passam pelo ScoringService, e não por INSERT direto: assim o
// seed produz ledger e budget_used pelas mesmas regras da API, sem chance de divergir.
$saleRepository = new SaleRepository($database);
$scoring = new ScoringService(
    $database,
    new CampaignRepository($database),
    new ProductRepository($database),
    new UserRepository($database),
    $saleRepository,
    new WalletEntryRepository($database),
);

$idOf = static function (string $sql, string $key) use ($pdo): int {
    $statement = $pdo->prepare($sql);
    $statement->execute([$key]);

    return (int) $statement->fetchColumn();
};

$adminId = $idOf('SELECT id FROM users WHERE email = ?', 'admin@toro.test');
$campaignId = $idOf('SELECT id FROM campaigns WHERE name = ?', $campaign[0]);

$demoSales = [
    ['SEED-0001', 'ana@toro.test', 'CDB-PRE', 12, '250.00'],
    ['SEED-0002', 'ana@toro.test', 'FUNDO-MM', 4, '1000.00'],
    ['SEED-0003', 'bruno@toro.test', 'CDB-POS', 8, '500.00'],
    ['SEED-0004', 'bruno@toro.test', 'TD-SELIC', 20, '100.00'],
    ['SEED-0005', 'carla@toro.test', 'FUNDO-MM', 6, '1000.00'],
];

$canceledExternalId = 'SEED-0004';
$createdSales = 0;

foreach ($demoSales as [$externalId, $email, $sku, $quantity, $unitValue]) {
    if ($saleRepository->findByExternalId($externalId) !== null) {
        continue;
    }

    $scoring->registerSale(
        $externalId,
        $campaignId,
        $idOf('SELECT id FROM users WHERE email = ?', $email),
        $idOf('SELECT id FROM products WHERE sku = ?', $sku),
        $quantity,
        $unitValue,
        $adminId,
    );

    $createdSales++;
}

// Uma venda nasce cancelada para o extrato do vendedor já mostrar crédito e débito.
$toCancel = $saleRepository->findByExternalId($canceledExternalId);
$canceledSales = 0;

if ($toCancel !== null && $toCancel->status === SaleStatus::Approved) {
    $scoring->cancelSale($canceledExternalId);
    $canceledSales++;
}

fwrite(STDOUT, sprintf(
    "seed: users=%d products=%d campaigns=%d sales=%d canceled=%d created\n",
    $createdUsers,
    $createdProducts,
    $createdCampaigns,
    $createdSales,
    $canceledSales,
));
