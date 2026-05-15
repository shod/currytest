<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\Currency;
use App\Models\ExchangeRate;
use App\Services\Currency\CurrencyConverter;
use App\Services\Currency\Exceptions\InvalidConversionAmountException;
use App\Services\Currency\Exceptions\MissingExchangeRateException;
use App\Services\Currency\Exceptions\UnsupportedCurrencyException;
use App\Services\Currency\ExchangeRateRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CurrencyConverterTest extends TestCase
{
    use RefreshDatabase;

    private CurrencyConverter $converter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->converter = new CurrencyConverter(new ExchangeRateRepository);
    }

    private function seedRate(string $targetCode, string $rate, string $baseCode = 'USD'): void
    {
        $currency = Currency::firstOrCreate(['code' => $targetCode], ['name' => $targetCode, 'is_enabled' => true]);
        ExchangeRate::create([
            'base_code' => $baseCode,
            'target_currency_id' => $currency->id,
            'rate' => $rate,
            'fetched_at' => now(),
        ]);
    }

    /** CC-01: Convert known pair against seeded rates */
    public function test_cc01_happy_path_conversion(): void
    {
        $this->seedRate('USD', '1.0000000000');
        $this->seedRate('RUB', '91.4000000000');

        $result = $this->converter->convert(100, 'USD', 'RUB');

        $this->assertEquals('9140.00', $result);
    }

    /** CC-02: Same source and target returns amount rounded 2dp */
    public function test_cc02_same_currency_short_circuit(): void
    {
        $result = $this->converter->convert('123.456', 'EUR', 'EUR');

        $this->assertEquals('123.46', $result);
    }

    /** CC-03: Case/whitespace normalisation */
    public function test_cc03_case_and_whitespace_normalisation(): void
    {
        $this->seedRate('USD', '1.0000000000');
        $this->seedRate('RUB', '91.4000000000');

        $result = $this->converter->convert(100, ' usd ', 'rub');

        $this->assertEquals('9140.00', $result);
    }

    /** CC-04: Zero amount returns "0.00" */
    public function test_cc04_zero_amount(): void
    {
        $result = $this->converter->convert(0, 'USD', 'RUB');

        $this->assertEquals('0.00', $result);
    }

    /** CC-05: Negative amount throws InvalidConversionAmountException */
    public function test_cc05_negative_amount_throws(): void
    {
        $this->expectException(InvalidConversionAmountException::class);

        $this->converter->convert(-1, 'USD', 'RUB');
    }

    /** CC-06: Unsupported source currency throws UnsupportedCurrencyException */
    public function test_cc06_unsupported_from_currency_throws(): void
    {
        $this->seedRate('RUB', '91.4000000000');

        $this->expectException(UnsupportedCurrencyException::class);

        $this->converter->convert(100, 'XYZ', 'RUB');
    }

    /** CC-07: Unsupported target currency throws UnsupportedCurrencyException */
    public function test_cc07_unsupported_to_currency_throws(): void
    {
        $this->seedRate('USD', '1.0000000000');

        $this->expectException(UnsupportedCurrencyException::class);

        $this->converter->convert(100, 'USD', 'XYZ');
    }

    /** CC-08: Cross-currency through base uses chained rate formula */
    public function test_cc08_cross_currency_through_base(): void
    {
        $this->seedRate('EUR', '0.9200000000');
        $this->seedRate('RUB', '91.4000000000');

        // EUR -> RUB: amount * rate(USD->RUB) / rate(USD->EUR)
        // 100 * 91.4 / 0.92 = 9130.434...  rounded = 9130.43
        $expected = bcdiv(bcmul('100', '91.4000000000', 10), '0.9200000000', 10);
        $expected = $this->roundHalfUp($expected, 2);

        $result = $this->converter->convert(100, 'EUR', 'RUB');

        $this->assertEquals($expected, $result);
    }

    /** CC-09: High-precision determinism — no float drift */
    public function test_cc09_high_precision_determinism(): void
    {
        Currency::firstOrCreate(['code' => 'USD'], ['name' => 'US Dollar', 'is_enabled' => true]);
        $this->seedRate('EUR', '0.9234567890');

        // USD is base currency; USD -> EUR: amount * rate(USD->EUR)
        $expected = bcmul('999999.99', '0.9234567890', 10);
        $expected = $this->roundHalfUp($expected, 2);

        $result = $this->converter->convert('999999.99', 'USD', 'EUR');

        $this->assertSame($expected, $result);
    }

    /** CC-10: Missing rate for supported currency throws MissingExchangeRateException */
    public function test_cc10_missing_rate_throws(): void
    {
        // Create supported currency but no exchange rate row
        Currency::create(['code' => 'USD', 'name' => 'US Dollar', 'is_enabled' => true]);
        Currency::create(['code' => 'RUB', 'name' => 'Russian Ruble', 'is_enabled' => true]);

        $this->expectException(MissingExchangeRateException::class);

        $this->converter->convert(100, 'USD', 'RUB');
    }

    /** CC-11: Non-numeric amount throws InvalidConversionAmountException */
    public function test_cc11_non_numeric_amount_throws(): void
    {
        $this->expectException(InvalidConversionAmountException::class);

        $this->converter->convert('abc', 'USD', 'RUB');
    }

    private function roundHalfUp(string $value, int $places): string
    {
        $factor = bcpow('10', (string) $places, 0);
        $shifted = bcmul($value, $factor, 10);
        // Extract integer part via bcmath (no float conversion)
        $intPart = bcadd($shifted, '0', 0);
        $remainder = bcsub($shifted, $intPart, 10);

        if (bccomp($remainder, '0.5', 10) >= 0) {
            $intPart = bcadd($intPart, '1', 0);
        }

        $result = bcdiv($intPart, $factor, $places);

        return number_format((float) $result, $places, '.', '');
    }
}
