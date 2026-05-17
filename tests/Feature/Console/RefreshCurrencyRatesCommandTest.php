<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Models\Currency;
use App\Models\RefreshJobLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RefreshCurrencyRatesCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        Config::set('currency.freecurrencyapi.api_key', 'test-api-key');

        Currency::create(['code' => 'EUR', 'name' => 'Euro', 'is_enabled' => true]);
        Currency::create(['code' => 'RUB', 'name' => 'Russian Ruble', 'is_enabled' => true]);
    }

    public function test_exits_zero_on_success(): void
    {
        Http::fake(['*' => Http::response(['data' => ['EUR' => 0.92, 'RUB' => 91.4]], 200)]);

        $this->artisan('currency:refresh-rates')
            ->assertExitCode(0);

        $this->assertEquals('success', RefreshJobLog::first()->status);
    }

    public function test_exits_non_zero_on_failure(): void
    {
        Http::fake(['*' => Http::response([], 500)]);

        $this->artisan('currency:refresh-rates')
            ->assertExitCode(1);
    }

    public function test_triggered_by_manual_when_run_directly(): void
    {
        Http::fake(['*' => Http::response(['data' => ['EUR' => 0.92, 'RUB' => 91.4]], 200)]);

        $this->artisan('currency:refresh-rates');

        $this->assertEquals('manual', RefreshJobLog::first()->triggered_by);
    }

    public function test_triggered_by_scheduler_when_option_provided(): void
    {
        Http::fake(['*' => Http::response(['data' => ['EUR' => 0.92, 'RUB' => 91.4]], 200)]);

        $this->artisan('currency:refresh-rates', ['--triggered-by' => 'scheduler']);

        $this->assertEquals('scheduler', RefreshJobLog::first()->triggered_by);
    }
}
