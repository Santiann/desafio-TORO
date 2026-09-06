<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Infrastructure\Database;
use PDOException;

final class HealthController
{
    public function __construct(private readonly Database $database)
    {
    }

    public function check(Request $request): Response
    {
        try {
            $this->database->pdo()->query('SELECT 1');
        } catch (PDOException $e) {
            error_log('health check failed: ' . $e->getMessage());

            return Response::json(['status' => 'degraded', 'database' => 'down'], 503);
        }

        return Response::json(['status' => 'ok', 'database' => 'up']);
    }
}
