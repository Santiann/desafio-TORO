<?php

declare(strict_types=1);

namespace App\Domain;

final readonly class Sale
{
    public function __construct(
        public int $id,
        public string $externalId,
        public int $campaignId,
        public int $sellerId,
        public int $productId,
        public int $quantity,
        public string $unitValue,
        public SaleStatus $status,
        public int $createdByUserId,
        public string $createdAt,
        public ?string $canceledAt,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'external_id' => $this->externalId,
            'campaign_id' => $this->campaignId,
            'seller_id' => $this->sellerId,
            'product_id' => $this->productId,
            'quantity' => $this->quantity,
            'unit_value' => $this->unitValue,
            'status' => $this->status->value,
            'created_by_user_id' => $this->createdByUserId,
            'created_at' => $this->createdAt,
            'canceled_at' => $this->canceledAt,
        ];
    }
}
