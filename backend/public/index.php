<?php

declare(strict_types=1);

use App\Infrastructure\Database;

$database = require dirname(__DIR__) . '/bootstrap.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

header('Content-Type: application/json; charset=utf-8');

if ($method === 'GET' && $path === '/health') {
    try {
        $database->pdo()->query('SELECT 1');

        http_response_code(200);
        echo json_encode(['status' => 'ok', 'database' => 'up']);
    } catch (PDOException $e) {
        error_log('health check failed: ' . $e->getMessage());

        http_response_code(503);
        echo json_encode(['status' => 'degraded', 'database' => 'down']);
    }

    return;
}

http_response_code(404);
echo json_encode(['error' => ['code' => 'not_found', 'message' => 'recurso não encontrado']]);
