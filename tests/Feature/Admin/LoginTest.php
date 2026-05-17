<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_returns_200_with_csrf_form(): void
    {
        $response = $this->get(route('admin.login'));

        $response->assertStatus(200);
        $response->assertSee('_token', false);
    }

    public function test_valid_credentials_redirect_to_rates_page(): void
    {
        User::factory()->admin()->create();

        $response = $this->post(route('admin.login.attempt'), [
            'username' => 'admin',
            'password' => 'Aqaz',
        ]);

        $response->assertRedirect(route('admin.rates.index'));
        $this->assertAuthenticated();
    }

    public function test_wrong_password_returns_validation_error(): void
    {
        User::factory()->admin()->create();

        $response = $this->post(route('admin.login.attempt'), [
            'username' => 'admin',
            'password' => 'wrong-password',
        ]);

        $response->assertSessionHasErrors('username');
        $this->assertGuest();
    }

    public function test_logout_invalidates_session(): void
    {
        $user = User::factory()->admin()->create();

        $this->actingAs($user)
            ->post(route('admin.logout'))
            ->assertRedirect(route('admin.login'));

        $this->assertGuest();
    }
}
