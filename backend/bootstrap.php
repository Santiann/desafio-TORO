<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use App\Infrastructure\Database;
use App\Support\Env;

Env::load(__DIR__);

return Database::fromEnv();
