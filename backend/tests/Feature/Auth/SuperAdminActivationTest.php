<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Models\AdminInvitation;
use App\Models\User;
use App\Services\AdminInvitationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class SuperAdminActivationTest extends TestCase
{
    use RefreshDatabase;

    private function seedPendingInvitation(string $email, string $plainToken): AdminInvitation
    {
        User::factory()->superAdmin()->unverified()->create(['email' => $email]);

        return AdminInvitation::query()->create([
            'email' => $email,
            'name' => 'Super Admin',
            'role' => UserRole::SuperAdmin,
            'permissions' => null,
            'token_hash' => hash('sha256', $plainToken),
            'invited_by' => null,
            'expires_at' => now()->addHour(),
        ]);
    }

    private function activationPayload(string $email, string $plainToken, ?int $invitationId = null): array
    {
        $payload = [
            'email' => $email,
            'token' => $plainToken,
            'password' => 'Password1!',
            'password_confirmation' => 'Password1!',
        ];

        if ($invitationId !== null) {
            $payload['invitation'] = $invitationId;
        }

        return $payload;
    }

    public function test_admin_activate_preflight_includes_cors_for_spa_origin(): void
    {
        $frontend = rtrim((string) config('app.frontend_url'), '/');

        $this->withHeaders([
            'Origin' => $frontend,
            'Access-Control-Request-Method' => 'POST',
        ])->options('/admin/activate')
            ->assertNoContent()
            ->assertHeader('Access-Control-Allow-Origin', $frontend)
            ->assertHeader('Access-Control-Allow-Credentials', 'true');
    }

    public function test_valid_token_activates_super_admin(): void
    {
        Notification::fake();
        $plain = str_repeat('a', 64);
        $invitation = $this->seedPendingInvitation('super@example.com', $plain);

        $this->postSpa('/admin/activate', $this->activationPayload('super@example.com', $plain, $invitation->id))
            ->assertOk();

        $user = User::query()->where('email', 'super@example.com')->firstOrFail();
        $this->assertTrue($user->hasVerifiedEmail());
        $this->assertSame(UserRole::SuperAdmin, $user->role);
        $this->assertNotNull($invitation->fresh()->accepted_at);
    }

    public function test_wrong_token_rejected(): void
    {
        Notification::fake();
        $this->seedPendingInvitation('super@example.com', str_repeat('a', 64));

        $this->postSpa('/admin/activate', $this->activationPayload('super@example.com', str_repeat('x', 64)))
            ->assertStatus(422)
            ->assertJsonPath('code', 'ACTIVATION_INVALID');
    }

    public function test_expired_token_rejected(): void
    {
        Notification::fake();
        $plain = str_repeat('b', 64);
        AdminInvitation::query()->create([
            'email' => 'expired@example.com',
            'name' => 'Expired',
            'role' => 'super_admin',
            'permissions' => null,
            'token_hash' => hash('sha256', $plain),
            'invited_by' => null,
            'expires_at' => now()->subMinute(),
        ]);

        $this->postSpa('/admin/activate', $this->activationPayload('expired@example.com', $plain))
            ->assertStatus(422)
            ->assertJsonPath('code', 'ACTIVATION_EXPIRED');
    }

    public function test_token_cannot_be_reused(): void
    {
        Notification::fake();
        $plain = str_repeat('c', 64);
        AdminInvitation::query()->create([
            'email' => 'reuse@example.com',
            'name' => 'Reuse',
            'role' => 'super_admin',
            'permissions' => null,
            'token_hash' => hash('sha256', $plain),
            'invited_by' => null,
            'expires_at' => now()->addHour(),
            'accepted_at' => now(),
        ]);

        $this->postSpa('/admin/activate', $this->activationPayload('reuse@example.com', $plain))
            ->assertStatus(422)
            ->assertJsonPath('code', 'ACTIVATION_USED');
    }

    public function test_superseded_token_after_resend(): void
    {
        Notification::fake();
        $service = app(AdminInvitationService::class);
        $service->createSuperAdminInvitation('Super', 'resend@example.com');

        $oldPlain = str_repeat('d', 64);
        $oldInvitationId = AdminInvitation::query()->where('email', 'resend@example.com')->value('id');
        AdminInvitation::query()->where('email', 'resend@example.com')->update([
            'token_hash' => hash('sha256', $oldPlain),
        ]);

        $service->resendSuperAdminActivation('resend@example.com');

        $this->postSpa('/admin/activate', $this->activationPayload('resend@example.com', $oldPlain, $oldInvitationId))
            ->assertStatus(422)
            ->assertJsonPath('code', 'ACTIVATION_SUPERSEDED');
    }

    public function test_password_validation_failure_does_not_consume_token(): void
    {
        Notification::fake();
        $plain = str_repeat('e', 64);
        $invitation = $this->seedPendingInvitation('pwd@example.com', $plain);

        $this->postSpa('/admin/activate', [
            'email' => 'pwd@example.com',
            'token' => $plain,
            'password' => 'short',
            'password_confirmation' => 'short',
        ])->assertStatus(422);

        $this->assertNull($invitation->fresh()->accepted_at);
    }

    public function test_successful_activation_only_once(): void
    {
        Notification::fake();
        $plain = str_repeat('f', 64);
        $invitation = $this->seedPendingInvitation('once@example.com', $plain);

        $this->postSpa('/admin/activate', $this->activationPayload('once@example.com', $plain, $invitation->id))
            ->assertOk();

        $this->postSpa('/admin/activate', $this->activationPayload('once@example.com', $plain, $invitation->id), false)
            ->assertStatus(422)
            ->assertJsonPath('code', 'ACTIVATION_USED');
    }

    public function test_activation_by_invitation_id_and_token(): void
    {
        Notification::fake();
        $plain = str_repeat('g', 64);
        $invitation = $this->seedPendingInvitation('id@example.com', $plain);

        $this->postSpa('/admin/activate', [
            'email' => 'wrong-but-id-matches@example.com',
            'invitation' => $invitation->id,
            'token' => $plain,
            'password' => 'Password1!',
            'password_confirmation' => 'Password1!',
        ])->assertOk();

        $this->assertTrue(User::query()->where('email', 'id@example.com')->firstOrFail()->hasVerifiedEmail());
    }

    public function test_unverified_super_admin_cannot_access_admin_api(): void
    {
        $super = User::factory()->superAdmin()->unverified()->create();

        $this->actingAs($super)
            ->getJson('/api/v1/admin/staff')
            ->assertForbidden();
    }

    public function test_activation_succeeds_without_sessions_table_when_session_driver_is_file(): void
    {
        Notification::fake();
        config(['session.driver' => 'file']);

        $plain = str_repeat('h', 64);
        $invitation = $this->seedPendingInvitation('filesession@example.com', $plain);

        $this->postSpa('/admin/activate', $this->activationPayload('filesession@example.com', $plain, $invitation->id))
            ->assertOk();

        $this->assertTrue(User::query()->where('email', 'filesession@example.com')->firstOrFail()->hasVerifiedEmail());
    }

    public function test_resend_activation_fails_when_already_verified(): void
    {
        User::factory()->superAdmin()->create(['email' => 'done@example.com']);

        $this->artisan('admin:create-super', [
            '--email' => 'done@example.com',
            '--resend-activation' => true,
        ])->assertFailed();
    }
}
