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
use App\Http\Controllers\HealthController;

require __DIR__ . '/vendor/autoload.php';

Env::load(__DIR__);

$database = Database::fromEnv();
$tokens = new TokenService(Env::required('JWT_SECRET'));

$healthController = new HealthController($database);
$authController = new AuthController(new AuthService(new UserRepository($database)), $tokens);
$pingController = new PingController();

$requireAuth = new RequireAuth($tokens);

$router = new Router();

$router->get('/health', [$healthController, 'check']);
$router->post('/auth/login', [$authController, 'login']);
$router->get('/admin/ping', [$pingController, 'pong'], [$requireAuth, new RequireRole(Role::Admin)]);
$router->get('/seller/ping', [$pingController, 'pong'], [$requireAuth, new RequireRole(Role::Seller)]);

return $router;
