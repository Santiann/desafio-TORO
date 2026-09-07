<?php

declare(strict_types=1);

namespace App\Domain;

final readonly class SaleSummary
{
    public function __construct(
        public Sale $sale,
        public ?int $points,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [...$this->sale->toArray(), 'points' => $this->points];
    }
}
