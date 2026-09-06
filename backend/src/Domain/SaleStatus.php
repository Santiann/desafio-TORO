<?php

declare(strict_types=1);

namespace App\Domain;

enum SaleStatus: string
{
    case Approved = 'approved';
    case Canceled = 'canceled';
}
