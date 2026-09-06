<?php

declare(strict_types=1);

namespace App\Domain;

use RuntimeException;

final class DuplicateExternalIdException extends RuntimeException
{
    public function __construct(public readonly string $externalId)
    {
        parent::__construct("sale already registered: {$externalId}");
    }
}
