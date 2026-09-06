<?php

declare(strict_types=1);

namespace App\Domain;

final readonly class CampaignOutcome
{
    public function __construct(
        public Campaign $campaign,
        public bool $applied,
    ) {
    }
}
