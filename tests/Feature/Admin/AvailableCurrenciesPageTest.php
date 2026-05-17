<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Currency;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AvailableCurrenciesPageTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create();
    }

    public function test_populated_state_lists_currencies(): void
    {
        Currency::create(['code' => 'USD', 'name' => 'US Dollar', 'is_enabled' => true]);
        Currency::create(['code' => 'EUR', 'name' => 'Euro', 'is_enabled' => false]);

        $response = $this->actingAs($this->admin)->get(route('admin.currencies.index'));

        $response->assertStatus(200);
        $response->assertSee('USD');
        $response->assertSee('US Dollar');
        $response->assertSee('Yes');
        $response->assertSee('EUR');
        $response->assertSee('No');
    }

    public function test_empty_state_shows_message(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.currencies.index'));

        $response->assertStatus(200);
        $response->assertSee('<table', false);
    }

    public function test_anonymous_redirects_to_login(): void
    {
        $response = $this->get(route('admin.currencies.index'));

        $response->assertRedirect(route('admin.login'));
    }

    public function test_non_admin_gets_403(): void
    {
        $user = User::factory()->create(['username' => null]);

        $response = $this->actingAs($user)->get(route('admin.currencies.index'));

        $response->assertStatus(403);
    }

    public function test_response_is_blade_html(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.currencies.index'));

        $response->assertStatus(200);
        $response->assertHeader('content-type', 'text/html; charset=UTF-8');
        $response->assertSee('<table', false);
    }
}
