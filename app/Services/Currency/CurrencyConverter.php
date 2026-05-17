<?php

declare(strict_types=1);

namespace App\Services\Currency;

use App\Services\Currency\Exceptions\InvalidConversionAmountException;

final class CurrencyConverter
{
    public function __construct(
        private readonly ExchangeRateRepository $rates,
    ) {}

    /**
     * Конвертирует денежную сумму из одной валюты в другую, используя сохранённые курсы.
     *
     * Нормализует коды валют (обрезает пробелы, переводит в верхний регистр), проверяет
     * корректность суммы, выполняет конвертацию через bcmath с точностью 10 знаков и
     * возвращает результат, округлённый до 2 знаков после запятой.
     *
     * @param  int|float|string  $amount  Сумма в исходной валюте
     * @param  string  $from  Код исходной валюты (ISO-4217, регистронезависимый)
     * @param  string  $to  Код целевой валюты (ISO-4217, регистронезависимый)
     * @return string Результат конвертации с 2 десятичными знаками
     */
    public function convert(int|float|string $amount, string $from, string $to): string
    {
        $rawFrom = $from;
        $rawTo = $to;

        $from = mb_strtoupper(trim($from), 'UTF-8');
        $to = mb_strtoupper(trim($to), 'UTF-8');

        $amountStr = (string) $amount;

        if (! is_numeric($amountStr)) {
            throw InvalidConversionAmountException::nonNumeric($amountStr);
        }

        if (bccomp($amountStr, '0', 10) < 0) {
            throw InvalidConversionAmountException::negative($amountStr);
        }

        if (bccomp($amountStr, '0', 10) === 0) {
            return '0.00';
        }

        if ($from === $to) {
            return $this->roundHalfUp($amountStr, 2);
        }

        $baseCurrency = config('currency.freecurrencyapi.base_currency', 'USD');

        $this->rates->requireCurrency($from);
        $this->rates->requireCurrency($to);

        if ($from === $baseCurrency) {
            $rate = $this->rates->findRate($baseCurrency, $to);
            $result = bcmul($amountStr, $rate, 10);
        } elseif ($to === $baseCurrency) {
            $rate = $this->rates->findRate($baseCurrency, $from);
            $result = bcdiv($amountStr, $rate, 10);
        } else {
            $rateTo = $this->rates->findRate($baseCurrency, $to);
            $rateFrom = $this->rates->findRate($baseCurrency, $from);
            $result = bcdiv(bcmul($amountStr, $rateTo, 10), $rateFrom, 10);
        }

        return $this->roundHalfUp($result, 2);
    }

    private function roundHalfUp(string $value, int $places): string
    {
        $factor = bcpow('10', (string) $places, 0);
        $shifted = bcmul($value, $factor, 10);
        $floored = floor((float) $shifted);
        $remainder = bcsub($shifted, number_format($floored, 0, '.', ''), 10);

        if (bccomp($remainder, '0.5', 10) >= 0) {
            $floored += 1;
        }

        return number_format($floored / (float) $factor, $places, '.', '');
    }
}
