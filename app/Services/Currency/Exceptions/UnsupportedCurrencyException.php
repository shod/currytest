<?php

declare(strict_types=1);

namespace App\Services\Currency\Exceptions;

use DomainException;

class UnsupportedCurrencyException extends DomainException
{
    public static function forCode(string $rawInput, string $normalised): self
    {
        return new self(
            "Currency code \"{$normalised}\" (input: \"{$rawInput}\") is not supported."
        );
    }
}
