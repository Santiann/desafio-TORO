<?php

declare(strict_types=1);

namespace App\Domain;

final readonly class WalletEntry
{
    public function __construct(
        public int $id,
        public int $sellerId,
        public int $campaignId,
        public int $saleId,
        public WalletEntryType $type,
        public int $points,
        public string $description,
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
            'seller_id' => $this->sellerId,
            'campaign_id' => $this->campaignId,
            'sale_id' => $this->saleId,
            'type' => $this->type->value,
            'points' => $this->points,
            'description' => $this->description,
            'created_at' => $this->createdAt,
        ];
    }
}
