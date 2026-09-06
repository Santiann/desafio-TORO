<?php

declare(strict_types=1);

namespace App\Domain;

final readonly class Campaign
{
    public function __construct(
        public int $id,
        public string $name,
        public int $budgetTotal,
        public int $budgetUsed,
        public string $startsAt,
        public string $endsAt,
        public CampaignStatus $status,
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
            'budget_total' => $this->budgetTotal,
            'budget_used' => $this->budgetUsed,
            'starts_at' => $this->startsAt,
            'ends_at' => $this->endsAt,
            'status' => $this->status->value,
            'created_at' => $this->createdAt,
        ];
    }
}
