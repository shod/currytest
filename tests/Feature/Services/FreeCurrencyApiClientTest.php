<?php

declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Services\Currency\Exceptions\MalformedRateResponseException;
use App\Services\Currency\FreeCurrencyApiClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FreeCurrencyApiClientTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        Config::set('currency.freecurrencyapi.api_key', 'test-api-key');
    }

    public function test_successful_payload_is_parsed_into_code_keyed_array(): void
    {
        Http::fake([
            '*' => Http::response([
                'data' => [
                    'EUR' => 0.923456789,
                    'RUB' => 91.4123,
                    'GBP' => 0.78921,
                ],
            ], 200),
        ]);

        $client = app(FreeCurrencyApiClient::class);
        $result = $client->fetchLatest('USD', ['EUR', 'RUB', 'GBP']);

        $this->assertArrayHasKey('EUR', $result);
        $this->assertArrayHasKey('RUB', $result);
        $this->assertArrayHasKey('GBP', $result);
        $this->assertEquals(0.923456789, $result['EUR']);
    }

    public function test_missing_data_key_raises_malformed_rate_response_exception(): void
    {
        Http::fake([
            '*' => Http::response(['errors' => ['apikey' => ['Invalid key']]], 401),
        ]);

        $this->expectException(MalformedRateResponseException::class);

        $client = app(FreeCurrencyApiClient::class);
        $client->fetchLatest('USD', ['EUR']);
    }

    public function test_missing_api_key_config_raises_runtime_exception(): void
    {
        Config::set('currency.freecurrencyapi.api_key', '');

        $this->expectException(\RuntimeException::class);

        $client = app(FreeCurrencyApiClient::class);
        $client->fetchLatest('USD', ['EUR']);
    }
}
