<?php

declare(strict_types=1);

use App\Support\Env;
use App\Domain\Role;
use App\Http\Router;
use App\Domain\AuthService;
use App\Domain\ScoringService;
use App\Infrastructure\Database;
use App\Http\Middleware\RequireAuth;
use App\Http\Middleware\RequireRole;
use App\Infrastructure\TokenService;
use App\Infrastructure\SaleRepository;
use App\Infrastructure\UserRepository;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\PingController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\SellerController;
use App\Infrastructure\ProductRepository;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\WalletController;
use App\Infrastructure\CampaignRepository;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\CampaignController;
use App\Infrastructure\WalletEntryRepository;

require __DIR__ . '/vendor/autoload.php';

Env::load(__DIR__);

$database = Database::fromEnv();
$tokens = new TokenService(Env::required('JWT_SECRET'));

$users = new UserRepository($database);
$products = new ProductRepository($database);
$campaigns = new CampaignRepository($database);
$sales = new SaleRepository($database);
$walletEntries = new WalletEntryRepository($database);

$scoring = new ScoringService($database, $campaigns, $products, $users, $sales, $walletEntries);

$healthController = new HealthController($database);
$authController = new AuthController(new AuthService($users), $tokens);
$pingController = new PingController();
$productController = new ProductController($products);
$campaignController = new CampaignController($campaigns);
$saleController = new SaleController($scoring);
$sellerController = new SellerController($users);
$walletController = new WalletController($walletEntries);

$requireAuth = new RequireAuth($tokens);
$adminOnly = [$requireAuth, new RequireRole(Role::Admin)];
$sellerOnly = [$requireAuth, new RequireRole(Role::Seller)];

$router = new Router();

$router->get('/health', [$healthController, 'check']);
$router->post('/auth/login', [$authController, 'login']);
$router->get('/admin/ping', [$pingController, 'pong'], $adminOnly);
$router->get('/seller/ping', [$pingController, 'pong'], $sellerOnly);

$router->get('/products', [$productController, 'index'], $adminOnly);
$router->post('/products', [$productController, 'store'], $adminOnly);
$router->put('/products/{id}', [$productController, 'update'], $adminOnly);
$router->delete('/products/{id}', [$productController, 'destroy'], $adminOnly);

$router->get('/campaigns', [$campaignController, 'index'], $adminOnly);
$router->post('/campaigns', [$campaignController, 'store'], $adminOnly);
$router->post('/campaigns/{id}/close', [$campaignController, 'close'], $adminOnly);

$router->get('/sellers', [$sellerController, 'index'], $adminOnly);

$router->post('/sales', [$saleController, 'store'], $adminOnly);
$router->post('/sales/{external_id}/cancel', [$saleController, 'cancel'], $adminOnly);

$router->get('/me/wallet', [$walletController, 'show'], $sellerOnly);

return $router;
