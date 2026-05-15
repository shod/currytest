<?php

declare(strict_types=1);

namespace App\Services\Currency;

use App\Models\Currency;
use App\Models\ExchangeRate;
use App\Services\Currency\Exceptions\MissingExchangeRateException;
use App\Services\Currency\Exceptions\UnsupportedCurrencyException;

class ExchangeRateRepository
{
    public function findRate(string $baseCode, string $targetCode): string
    {
        $currency = Currency::where('code', $targetCode)->first();

        if ($currency === null) {
            throw MissingExchangeRateException::forCode($targetCode);
        }

        $rate = ExchangeRate::where('base_code', $baseCode)
            ->where('target_currency_id', $currency->id)
            ->value('rate');

        if ($rate === null) {
            throw MissingExchangeRateException::forCode($targetCode);
        }

        return (string) $rate;
    }

    public function requireCurrency(string $code): Currency
    {
        $currency = Currency::where('code', $code)->first();

        if ($currency === null) {
            throw UnsupportedCurrencyException::forCode($code, $code);
        }

        return $currency;
    }
}
