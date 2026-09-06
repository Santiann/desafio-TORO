<?php

declare(strict_types=1);

namespace App\Domain;

final readonly class Identity
{
    public function __construct(
        public int $id,
        public Role $role,
    ) {
    }
}
