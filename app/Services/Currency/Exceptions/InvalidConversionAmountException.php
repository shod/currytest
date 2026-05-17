<?php

declare(strict_types=1);

namespace App\Services\Currency\Exceptions;

use DomainException;

class InvalidConversionAmountException extends DomainException
{
    public static function negative(string $raw): self
    {
        return new self("Amount must be non-negative; got \"{$raw}\".");
    }

    public static function nonNumeric(mixed $raw): self
    {
        return new self("Amount must be numeric; got \"{$raw}\".");
    }
}
