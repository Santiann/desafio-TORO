<?php

declare(strict_types=1);

namespace App\Domain;

enum Role: string
{
    case Admin = 'admin';
    case Seller = 'seller';
}
