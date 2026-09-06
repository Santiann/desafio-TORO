<?php

declare(strict_types=1);

namespace App\Domain;

final readonly class SaleOutcome
{
    public function __construct(
        public Sale $sale,
        public bool $applied,
    ) {
    }
}
