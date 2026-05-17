<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Currency;
use App\Models\ExchangeRate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CurrencyRatesPageTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create();
    }

    public function test_empty_state_when_no_rates_seeded(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.rates.index'));

        $response->assertStatus(200);
        $response->assertSee('<table', false);
    }

    public function test_populated_state_lists_stored_rates(): void
    {
        $currency = Currency::create(['code' => 'EUR', 'name' => 'Euro', 'is_enabled' => true]);
        ExchangeRate::create([
            'base_code' => 'USD',
            'target_currency_id' => $currency->id,
            'rate' => '0.9200000000',
            'fetched_at' => now(),
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.rates.index'));

        $response->assertStatus(200);
        $response->assertSee('EUR');
        $response->assertSee('USD');
        $response->assertSee('<table', false);
    }

    public function test_anonymous_redirects_to_login(): void
    {
        $response = $this->get(route('admin.rates.index'));

        $response->assertRedirect(route('admin.login'));
    }

    public function test_non_admin_gets_403(): void
    {
        $user = User::factory()->create(['username' => null]);

        $response = $this->actingAs($user)->get(route('admin.rates.index'));

        $response->assertStatus(403);
    }

    public function test_response_is_html_with_table(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.rates.index'));

        $response->assertStatus(200);
        $response->assertHeader('content-type', 'text/html; charset=UTF-8');
        $response->assertSee('<table', false);
    }
}
