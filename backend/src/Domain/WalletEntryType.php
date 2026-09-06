<?php

declare(strict_types=1);

namespace App\Domain;

enum WalletEntryType: string
{
    case Credit = 'credit';
    case Debit = 'debit';
}
