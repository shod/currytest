<?php

declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Models\Currency;
use App\Models\ExchangeRate;
use App\Services\Currency\RateRefresher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RateRefresherTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        Config::set('currency.freecurrencyapi.api_key', 'test-api-key');

        foreach (['EUR', 'RUB', 'GBP'] as $code) {
            Currency::create(['code' => $code, 'name' => $code, 'is_enabled' => true]);
        }
    }

    public function test_successful_refresh_upserts_rates_and_creates_success_log(): void
    {
        Http::fake(['*' => Http::response(['data' => ['EUR' => 0.92, 'RUB' => 91.4, 'GBP' => 0.79]], 200)]);

        $log = app(RateRefresher::class)->run('manual');

        $this->assertEquals('success', $log->status);
        $this->assertEquals(3, $log->currencies_updated);
        $this->assertNotNull($log->finished_at);
        $this->assertEquals('manual', $log->triggered_by);
        $this->assertEquals(3, ExchangeRate::count());
    }

    public function test_provider_failure_leaves_rates_intact_and_creates_failure_log(): void
    {
        $eur = Currency::where('code', 'EUR')->first();
        ExchangeRate::create([
            'base_code' => 'USD',
            'target_currency_id' => $eur->id,
            'rate' => '0.8900000000',
            'fetched_at' => now()->subDay(),
        ]);

        Http::fake(['*' => Http::response([], 500)]);

        $log = app(RateRefresher::class)->run('manual');

        $this->assertEquals('failure', $log->status);
        $this->assertNotNull($log->finished_at);
        $this->assertDatabaseHas('exchange_rates', ['rate' => '0.8900000000']);
        $this->assertEquals(1, ExchangeRate::count());
    }

    public function test_concurrent_invocation_while_lock_held_creates_skipped_overlap_log(): void
    {
        $lock = Cache::lock('currency:refresh', 600);
        $lock->acquire();

        try {
            $log = app(RateRefresher::class)->run('manual');

            $this->assertEquals('skipped_overlap', $log->status);
            $this->assertEquals(0, ExchangeRate::count());
        } finally {
            $lock->release();
        }
    }

    public function test_partial_response_leaves_rates_untouched_and_creates_failure_log(): void
    {
        // Only EUR in response — GBP and RUB are missing
        Http::fake(['*' => Http::response(['data' => ['EUR' => 0.92]], 200)]);

        $log = app(RateRefresher::class)->run('manual');

        $this->assertEquals('failure', $log->status);
        $this->assertEquals(0, ExchangeRate::count(), 'No partial writes should occur');
    }
}
