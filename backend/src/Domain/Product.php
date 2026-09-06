<?php

declare(strict_types=1);

namespace App\Domain;

final readonly class Product
{
    public function __construct(
        public int $id,
        public string $name,
        public string $sku,
        public int $pointsPerUnit,
        public bool $active,
        public string $createdAt,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'sku' => $this->sku,
            'points_per_unit' => $this->pointsPerUnit,
            'active' => $this->active,
            'created_at' => $this->createdAt,
        ];
    }
}
