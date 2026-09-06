<?php

declare(strict_types=1);

use App\Infrastructure\Database;
use App\Infrastructure\Migrator;
use App\Support\Env;

require dirname(__DIR__) . '/vendor/autoload.php';

Env::load(dirname(__DIR__));

$pdo = Database::fromEnv()->connectWithRetry(30, 2);
$executed = (new Migrator($pdo, dirname(__DIR__) . '/migrations'))->run();

if ($executed === []) {
    fwrite(STDOUT, "migrations: nothing to apply\n");

    return;
}

foreach ($executed as $name) {
    fwrite(STDOUT, "migrations: applied {$name}\n");
}
