<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_email_can_be_verified_with_valid_signed_url(): void
    {
        $user = User::factory()->unverified()->create();

        $url = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->email)],
        );

        $response = $this->get($url);

        $response->assertRedirect(config('app.frontend_url').'/email-verified?status=success');
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
    }

    public function test_email_verification_rejects_invalid_signature(): void
    {
        $user = User::factory()->unverified()->create();

        $url = url("/email/verify/{$user->id}/".sha1($user->email));

        $response = $this->get($url);

        $response->assertRedirect(config('app.frontend_url').'/email-verified?status=invalid');
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_email_verification_rejects_expired_link(): void
    {
        $user = User::factory()->unverified()->create();

        $url = URL::temporarySignedRoute(
            'verification.verify',
            now()->subMinute(),
            ['id' => $user->id, 'hash' => sha1($user->email)],
        );

        $response = $this->get($url);

        $response->assertRedirect(config('app.frontend_url').'/email-verified?status=invalid');
    }

    public function test_email_verification_rejects_wrong_hash(): void
    {
        $user = User::factory()->unverified()->create();

        $url = URL::temporarySignedRoute(
            'verification.verify',
            now()->addHour(),
            ['id' => $user->id, 'hash' => sha1('wrong@example.com')],
        );

        $response = $this->get($url);

        $response->assertRedirect(config('app.frontend_url').'/email-verified?status=invalid');
    }

    public function test_email_verification_works_without_session(): void
    {
        $user = User::factory()->unverified()->create();

        $this->assertGuest();

        $url = URL::temporarySignedRoute(
            'verification.verify',
            now()->addHour(),
            ['id' => $user->id, 'hash' => sha1($user->email)],
        );

        $this->get($url)->assertRedirect();

        $this->assertTrue($user->fresh()->hasVerifiedEmail());
    }

    public function test_verifying_already_verified_email_is_idempotent(): void
    {
        $user = User::factory()->create();

        $url = URL::temporarySignedRoute(
            'verification.verify',
            now()->addHour(),
            ['id' => $user->id, 'hash' => sha1($user->email)],
        );

        $this->get($url)->assertRedirect(config('app.frontend_url').'/email-verified?status=already');
        $this->get($url)->assertRedirect(config('app.frontend_url').'/email-verified?status=already');
    }

    public function test_authenticated_user_can_resend_verification(): void
    {
        Notification::fake();

        $user = User::factory()->unverified()->create();
        $this->actingAs($user);

        $response = $this->postSpa('/email/verification-notification');

        $response->assertOk();
        Notification::assertSentTo($user, VerifyEmailNotification::class);
    }

    public function test_resend_verification_when_already_verified(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->postSpa('/email/verification-notification');

        $response->assertOk()
            ->assertJsonPath('code', 'EMAIL_ALREADY_VERIFIED');

        Notification::assertNothingSent();
    }

    public function test_resend_verification_is_throttled(): void
    {
        Notification::fake();

        $user = User::factory()->unverified()->create();
        $this->actingAs($user);

        for ($i = 0; $i < 6; $i++) {
            $this->postSpa('/email/verification-notification');
        }

        $this->postSpa('/email/verification-notification')->assertStatus(429);
    }

    public function test_changing_email_resets_verification_and_sends_notification(): void
    {
        Notification::fake();

        $user = User::factory()->create(['email' => 'old@example.com']);
        $this->actingAs($user);

        $response = $this->patchSpa('/api/v1/me', [
            'email' => 'new@example.com',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.is_email_verified', false);

        $this->assertNull($user->fresh()->email_verified_at);
        Notification::assertSentTo($user->fresh(), VerifyEmailNotification::class);
    }
}
