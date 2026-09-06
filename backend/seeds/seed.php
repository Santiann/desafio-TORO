<?php

declare(strict_types=1);

use App\Infrastructure\Database;
use App\Support\Env;

require dirname(__DIR__) . '/vendor/autoload.php';

Env::load(dirname(__DIR__));

$pdo = Database::fromEnv()->connectWithRetry(30, 2);
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

fwrite(STDOUT, sprintf(
    "seed: users=%d products=%d campaigns=%d created\n",
    $createdUsers,
    $createdProducts,
    $createdCampaigns,
));
