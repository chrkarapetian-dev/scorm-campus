<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_array_emails_are_validation_errors_in_production(): void
    {
        config(['app.debug' => false]);

        foreach (['/login', '/register'] as $url) {
            $this->postJson($url, [
                'name' => 'Սովորող', 'email' => ['invalid@example.com'],
                'password' => 'password123', 'password_confirmation' => 'password123',
            ])->assertUnprocessable()->assertJsonValidationErrors('email');
        }

        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_email_normalization_and_login_still_work(): void
    {
        $this->post('/register', [
            'name' => 'Սովորող', 'email' => 'LEARNER@example.com',
            'password' => 'password123', 'password_confirmation' => 'password123',
        ])->assertRedirect(route('home'));
        $user = User::where('email', 'learner@example.com')->firstOrFail();
        $this->assertAuthenticatedAs($user);

        $this->post('/logout')->assertRedirect(route('home'));
        $this->assertGuest();
        $this->post('/login', ['email' => 'LEARNER@example.com', 'password' => 'password123'])
            ->assertRedirect(route('home'));
        $this->assertAuthenticatedAs($user);
    }
}
