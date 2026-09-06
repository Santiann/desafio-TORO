<?php

declare(strict_types=1);

namespace App\Domain;

enum CampaignStatus: string
{
    case Active = 'active';
    case Closed = 'closed';
}
