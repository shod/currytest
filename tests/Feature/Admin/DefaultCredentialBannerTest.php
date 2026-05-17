<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DefaultCredentialBannerTest extends TestCase
{
    use RefreshDatabase;

    public function test_banner_renders_in_production_when_default_password_in_use(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        User::factory()->admin()->create();
        $admin = User::where('username', 'admin')->first();

        $response = $this->actingAs($admin)->get(route('admin.rates.index'));

        $response->assertStatus(200);
        $response->assertSee('role="alert"', false);
    }

    public function test_banner_absent_in_local_environment(): void
    {
        $this->app->detectEnvironment(fn () => 'local');
        User::factory()->admin()->create();
        $admin = User::where('username', 'admin')->first();

        $response = $this->actingAs($admin)->get(route('admin.rates.index'));

        $response->assertStatus(200);
        $response->assertDontSee('role="alert"', false);
    }
}
