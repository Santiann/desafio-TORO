<?php

declare(strict_types=1);

use App\Support\Env;
use App\Domain\Role;
use App\Http\Router;
use App\Domain\AuthService;
use App\Infrastructure\Database;
use App\Http\Middleware\RequireAuth;
use App\Http\Middleware\RequireRole;
use App\Infrastructure\TokenService;
use App\Infrastructure\UserRepository;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\PingController;
use App\Infrastructure\ProductRepository;
use App\Http\Controllers\HealthController;
use App\Infrastructure\CampaignRepository;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\CampaignController;

require __DIR__ . '/vendor/autoload.php';

Env::load(__DIR__);

$database = Database::fromEnv();
$tokens = new TokenService(Env::required('JWT_SECRET'));

$healthController = new HealthController($database);
$authController = new AuthController(new AuthService(new UserRepository($database)), $tokens);
$pingController = new PingController();
$productController = new ProductController(new ProductRepository($database));
$campaignController = new CampaignController(new CampaignRepository($database));

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

return $router;
