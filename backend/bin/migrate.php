<?php

declare(strict_types=1);

use App\Infrastructure\Database;
use App\Infrastructure\Migrator;

$database = require dirname(__DIR__) . '/bootstrap.php';

$pdo = $database->connectWithRetry(30, 2);
$executed = (new Migrator($pdo, dirname(__DIR__) . '/migrations'))->run();

if ($executed === []) {
    fwrite(STDOUT, "migrations: nothing to apply\n");

    return;
}

foreach ($executed as $name) {
    fwrite(STDOUT, "migrations: applied {$name}\n");
}
