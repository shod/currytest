<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Models\Currency;
use App\Models\ExchangeRate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConvertCurrencyCommandTest extends TestCase
{
    use RefreshDatabase;

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

    public function test_successful_conversion_outputs_correct_line_and_exits_zero(): void
    {
        $this->seedRate('USD', '1.0000000000');
        $this->seedRate('RUB', '91.4000000000');

        $this->artisan('currency:convert', ['amount' => '100', 'from' => 'USD', 'to' => 'RUB'])
            ->expectsOutput('USD 100 -> RUB 9140.00')
            ->assertExitCode(0);
    }

    public function test_non_numeric_input_prints_error_and_exits_one(): void
    {
        $this->artisan('currency:convert', ['amount' => 'abc', 'from' => 'USD', 'to' => 'RUB'])
            ->assertExitCode(1);
    }

    public function test_negative_amount_prints_error_and_exits_one(): void
    {
        $this->artisan('currency:convert', ['amount' => '-5', 'from' => 'USD', 'to' => 'RUB'])
            ->assertExitCode(1);
    }

    public function test_unsupported_currency_exits_two(): void
    {
        $this->artisan('currency:convert', ['amount' => '100', 'from' => 'USD', 'to' => 'XYZ'])
            ->assertExitCode(2);
    }

    public function test_missing_rate_exits_two(): void
    {
        Currency::create(['code' => 'USD', 'name' => 'US Dollar', 'is_enabled' => true]);
        Currency::create(['code' => 'RUB', 'name' => 'Russian Ruble', 'is_enabled' => true]);

        $this->artisan('currency:convert', ['amount' => '100', 'from' => 'USD', 'to' => 'RUB'])
            ->assertExitCode(2);
    }
}
