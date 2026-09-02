<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
    }

    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        $user = User::factory()->create();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_login_api_authenticates_and_returns_redirect(): void
    {
        $user = User::factory()->create();

        $response = $this->postJson('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->assertStringContainsString('/dashboard', (string) $response->json('redirect'));
        $this->assertAuthenticated();
    }

    public function test_login_api_says_when_the_email_does_not_exist(): void
    {
        $this->postJson('/login', [
            'email' => 'missing@example.com',
            'password' => 'password',
            'locale' => 'en',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email')
            ->assertJsonPath('errors.email.0', trans('auth.email_not_found', [], 'en'))
            ->assertJsonMissingPath('errors.password');

        $this->assertGuest();
    }

    public function test_login_api_says_when_the_password_is_wrong(): void
    {
        $user = User::factory()->create();

        $this->postJson('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
            'locale' => 'en',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('password')
            ->assertJsonPath('errors.password.0', trans('auth.wrong_password', [], 'en'))
            ->assertJsonMissingPath('errors.email');

        $this->assertGuest();
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])
            ->assertSessionHasErrors('password')
            ->assertSessionDoesntHaveErrors('email');

        $this->assertGuest();
    }

    public function test_unknown_email_fails_on_the_email_field(): void
    {
        $this->post('/login', [
            'email' => 'nobody@example.com',
            'password' => 'password',
        ])
            ->assertSessionHasErrors('email')
            ->assertSessionDoesntHaveErrors('password');

        $this->assertGuest();
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect('/');
    }
}
