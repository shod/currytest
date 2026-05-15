<?php

declare(strict_types=1);

namespace App\Services\Currency\Exceptions;

use RuntimeException;

class MissingExchangeRateException extends RuntimeException
{
    public static function forCode(string $code): self
    {
        return new self("No exchange rate found for currency code \"{$code}\".");
    }
}
