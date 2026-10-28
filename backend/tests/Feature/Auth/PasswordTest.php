<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordTest extends TestCase
{
    use RefreshDatabase;

    public function test_forgot_password_sends_notification(): void
    {
        Notification::fake();

        $user = User::factory()->create(['email' => 'reset@example.com']);

        $response = $this->postSpa('/forgot-password', [
            'email' => 'reset@example.com',
        ]);

        $response->assertOk();
        Notification::assertSentTo($user, ResetPasswordNotification::class);
    }

    public function test_forgot_password_does_not_reveal_missing_account(): void
    {
        Notification::fake();

        $response = $this->postSpa('/forgot-password', [
            'email' => 'missing@example.com',
        ]);

        $response->assertOk();
        Notification::assertNothingSent();
    }

    public function test_password_can_be_reset_with_valid_token(): void
    {
        Event::fake([PasswordReset::class]);

        $user = User::factory()->unverified()->create(['email' => 'reset@example.com']);
        $token = Password::createToken($user);

        DB::table('sessions')->insert([
            'id' => 'old-session',
            'user_id' => $user->id,
            'payload' => 'test',
            'last_activity' => time(),
        ]);

        $response = $this->postSpa('/reset-password', [
            'email' => 'reset@example.com',
            'token' => $token,
            'password' => 'NewPassword1',
            'password_confirmation' => 'NewPassword1',
        ]);

        $response->assertOk();
        $this->assertTrue(Hash::check('NewPassword1', $user->fresh()->password));
        $this->assertNull($user->fresh()->email_verified_at);
        $this->assertDatabaseMissing('sessions', ['id' => 'old-session']);
        Event::assertDispatched(PasswordReset::class);
    }

    public function test_reset_password_rejects_invalid_token(): void
    {
        User::factory()->create(['email' => 'reset@example.com']);

        $response = $this->postSpa('/reset-password', [
            'email' => 'reset@example.com',
            'token' => 'invalid-token',
            'password' => 'NewPassword1',
            'password_confirmation' => 'NewPassword1',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('code', 'INVALID_RESET_TOKEN');
    }

    public function test_reset_token_cannot_be_reused(): void
    {
        $user = User::factory()->create(['email' => 'reset@example.com']);
        $token = Password::createToken($user);

        $this->postSpa('/reset-password', [
            'email' => 'reset@example.com',
            'token' => $token,
            'password' => 'NewPassword1',
            'password_confirmation' => 'NewPassword1',
        ])->assertOk();

        $this->postSpa('/reset-password', [
            'email' => 'reset@example.com',
            'token' => $token,
            'password' => 'AnotherPass1',
            'password_confirmation' => 'AnotherPass1',
        ])->assertStatus(422);
    }

    public function test_change_password_requires_current_password(): void
    {
        $user = User::factory()->create(['password' => 'Password1']);
        $this->actingAs($user);

        $response = $this->patchSpa('/api/v1/me/password', [
            'current_password' => 'WrongPass1',
            'password' => 'NewPassword1',
            'password_confirmation' => 'NewPassword1',
        ]);

        $response->assertStatus(422);
    }

    public function test_change_password_invalidates_other_sessions(): void
    {
        $user = User::factory()->create(['password' => 'Password1']);

        DB::table('sessions')->insert([
            [
                'id' => 'session-a',
                'user_id' => $user->id,
                'payload' => base64_encode(serialize([])),
                'last_activity' => time(),
            ],
            [
                'id' => 'session-b',
                'user_id' => $user->id,
                'payload' => base64_encode(serialize([])),
                'last_activity' => time(),
            ],
        ]);

        $this->actingAs($user);

        $this->patchSpa('/api/v1/me/password', [
            'current_password' => 'Password1',
            'password' => 'NewPassword1',
            'password_confirmation' => 'NewPassword1',
        ])->assertOk();

        $this->assertDatabaseMissing('sessions', ['id' => 'session-a']);
        $this->assertDatabaseMissing('sessions', ['id' => 'session-b']);
    }
}
