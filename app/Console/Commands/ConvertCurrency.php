<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Currency\CurrencyConverter;
use App\Services\Currency\Exceptions\InvalidConversionAmountException;
use App\Services\Currency\Exceptions\MissingExchangeRateException;
use App\Services\Currency\Exceptions\UnsupportedCurrencyException;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('currency:convert
    {amount : Amount in the source currency (numeric string or float)}
    {from   : Source currency code (ISO-4217; case-insensitive)}
    {to     : Target currency code (ISO-4217; case-insensitive)}')]
#[Description('Convert an amount between two supported currencies using stored rates.')]
class ConvertCurrency extends Command
{
    public function __construct(private readonly CurrencyConverter $converter)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $amount = $this->argument('amount');
        $from = mb_strtoupper(trim((string) $this->argument('from')), 'UTF-8');
        $to = mb_strtoupper(trim((string) $this->argument('to')), 'UTF-8');

        try {
            $result = $this->converter->convert($amount, $from, $to);
        } catch (InvalidConversionAmountException $e) {
            fwrite(STDERR, 'error: '.$e->getMessage().PHP_EOL);

            return 1;
        } catch (UnsupportedCurrencyException|MissingExchangeRateException $e) {
            fwrite(STDERR, 'error: '.$e->getMessage().PHP_EOL);

            return 2;
        }

        $this->line("{$from} {$amount} -> {$to} {$result}");

        return 0;
    }
}
