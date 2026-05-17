<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EnsureUserIsAdminMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    public function test_anonymous_request_redirects_to_admin_login(): void
    {
        $response = $this->get(route('admin.rates.index'));

        $response->assertRedirect(route('admin.login'));
    }

    public function test_authenticated_non_admin_user_gets_403(): void
    {
        $user = User::factory()->create(['username' => null]);

        $response = $this->actingAs($user)->get(route('admin.rates.index'));

        $response->assertStatus(403);
    }

    public function test_authenticated_admin_user_passes_through(): void
    {
        $user = User::factory()->admin()->create();

        $response = $this->actingAs($user)->get(route('admin.rates.index'));

        $response->assertStatus(200);
    }
}
