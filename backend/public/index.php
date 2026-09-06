<?php

declare(strict_types=1);

use App\Http\Request;
use App\Http\Response;
use App\Support\HttpException;

set_exception_handler(static function (Throwable $e): void {
    error_log(sprintf('bootstrap failure: %s: %s', $e::class, $e->getMessage()));

    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
    }

    echo '{"error":{"code":"internal_error","message":"erro interno"}}';
});

$router = require dirname(__DIR__) . '/bootstrap.php';

try {
    $response = $router->dispatch(Request::fromGlobals());
} catch (HttpException $e) {
    $response = Response::error($e);
} catch (Throwable $e) {
    error_log(sprintf('unhandled: %s: %s at %s:%d', $e::class, $e->getMessage(), $e->getFile(), $e->getLine()));

    $response = Response::internalError();
}

$response->send();
