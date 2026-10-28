<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_login_with_valid_credentials(): void
    {
        $user = User::factory()->create([
            'email' => 'login@example.com',
            'password' => 'Password1',
        ]);

        $response = $this->postSpa('/login', [
            'email' => 'login@example.com',
            'password' => 'Password1',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.email', 'login@example.com');

        $this->assertAuthenticatedAs($user);
    }

    public function test_login_fails_with_wrong_password(): void
    {
        User::factory()->create([
            'email' => 'login@example.com',
            'password' => 'Password1',
        ]);

        $response = $this->postSpa('/login', [
            'email' => 'login@example.com',
            'password' => 'WrongPass1',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('code', 'INVALID_CREDENTIALS');
    }

    public function test_blocked_user_cannot_login(): void
    {
        User::factory()->blocked()->create([
            'email' => 'blocked@example.com',
            'password' => 'Password1',
        ]);

        $response = $this->postSpa('/login', [
            'email' => 'blocked@example.com',
            'password' => 'Password1',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('code', 'ACCOUNT_BLOCKED');
    }

    public function test_login_regenerates_session(): void
    {
        User::factory()->create([
            'email' => 'session@example.com',
            'password' => 'Password1',
        ]);

        $this->initializeSpaSession();
        $oldSession = session()->getId();

        $this->postSpa('/login', [
            'email' => 'session@example.com',
            'password' => 'Password1',
        ]);

        $this->assertNotSame($oldSession, session()->getId());
    }

    public function test_unverified_user_can_login(): void
    {
        $user = User::factory()->unverified()->create([
            'email' => 'unverified@example.com',
            'password' => 'Password1',
        ]);

        $response = $this->postSpa('/login', [
            'email' => 'unverified@example.com',
            'password' => 'Password1',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.is_email_verified', false);

        $this->assertAuthenticatedAs($user);
    }

    public function test_login_is_rate_limited(): void
    {
        User::factory()->create([
            'email' => 'rate@example.com',
            'password' => 'Password1',
        ]);

        RateLimiter::clear('rate@example.com|127.0.0.1');

        for ($i = 0; $i < 5; $i++) {
            $this->postSpa('/login', [
                'email' => 'rate@example.com',
                'password' => 'WrongPass1',
            ]);
        }

        $response = $this->postSpa('/login', [
            'email' => 'rate@example.com',
            'password' => 'WrongPass1',
        ]);

        $response->assertStatus(429);
    }
}
