<?php

namespace Tests\Feature\Auth;

use App\Enums\AdminPermission;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\AdminInvitation;
use App\Models\User;
use App\Services\AdminInvitationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class RbacTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_cannot_access_admin_dashboard(): void
    {
        $this->actingAs(User::factory()->create())
            ->getJson('/api/v1/admin/dashboard')
            ->assertForbidden();
    }

    public function test_admin_without_permission_cannot_access_books(): void
    {
        $admin = User::factory()->withPermissions([AdminPermission::Dashboard->value])->create();
        $this->actingAs($admin);

        $this->getJson('/api/v1/admin/dashboard')->assertOk();
        $this->getJson('/api/v1/admin/books')->assertForbidden();
    }

    public function test_super_admin_can_access_staff_management(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create())
            ->getJson('/api/v1/admin/staff')
            ->assertOk()
            ->assertJsonStructure(['data' => ['staff', 'pending_invitations', 'available_permissions']]);
    }

    public function test_admin_cannot_access_staff_management(): void
    {
        $this->actingAsAdmin();
        $this->getJson('/api/v1/admin/staff')->assertForbidden();
    }

    public function test_super_admin_can_invite_admin(): void
    {
        Notification::fake();

        $super = User::factory()->superAdmin()->create();

        $this->actingAs($super)
            ->postJson('/api/v1/admin/staff/invitations', [
                'name' => 'Ops Admin',
                'email' => 'ops@example.com',
                'permissions' => [AdminPermission::Orders->value, AdminPermission::Dashboard->value],
            ])
            ->assertCreated();

        $this->assertDatabaseHas('admin_invitations', [
            'email' => 'ops@example.com',
            'role' => UserRole::Admin->value,
        ]);
    }

    public function test_admin_cannot_elevate_via_registration(): void
    {
        Notification::fake();

        $this->postSpa('/register', [
            'name' => 'Hacker',
            'email' => 'hack@example.com',
            'password' => 'Password1',
            'password_confirmation' => 'Password1',
            'role' => 'super_admin',
        ])->assertCreated();

        $this->assertDatabaseHas('users', [
            'email' => 'hack@example.com',
            'role' => UserRole::Customer->value,
        ]);
    }

    public function test_cannot_block_last_super_admin(): void
    {
        $super = User::factory()->superAdmin()->create();

        $this->actingAs($super)
            ->patchJson('/api/v1/admin/staff/users/'.$super->id.'/status', [
                'status' => UserStatus::Blocked->value,
            ])
            ->assertStatus(409)
            ->assertJsonPath('code', 'LAST_SUPER_ADMIN');
    }

    public function test_admin_activation_sets_password_and_permissions(): void
    {
        $service = app(AdminInvitationService::class);
        Notification::fake();

        $super = User::factory()->superAdmin()->create();
        $this->actingAs($super);

        $this->postJson('/api/v1/admin/staff/invitations', [
            'name' => 'Module Admin',
            'email' => 'module@example.com',
            'permissions' => [AdminPermission::Books->value],
        ])->assertCreated();

        /** @var AdminInvitation $invitation */
        $invitation = AdminInvitation::query()->where('email', 'module@example.com')->firstOrFail();
        $plain = 'test-token-'.str_repeat('a', 40);

        $invitation->update(['token_hash' => hash('sha256', $plain)]);

        $this->postSpa('/admin/activate', [
            'email' => 'module@example.com',
            'token' => $plain,
            'password' => 'Password1!',
            'password_confirmation' => 'Password1!',
        ])->assertOk();

        $user = User::query()->where('email', 'module@example.com')->firstOrFail();
        $this->assertTrue($user->hasVerifiedEmail());
        $this->assertSame(UserRole::Admin, $user->role);
        $this->assertSame([AdminPermission::Books->value], $user->admin_permissions);
        $this->assertTrue($user->hasAdminPermission(AdminPermission::Books->value));
        $this->assertFalse($user->hasAdminPermission(AdminPermission::Orders->value));
    }

    public function test_create_super_admin_command_requires_single_super_admin(): void
    {
        Notification::fake();

        User::factory()->superAdmin()->create(['email' => 'existing@example.com']);

        $this->artisan('admin:create-super', [
            '--name' => 'Another',
            '--email' => 'another@example.com',
            '--force' => true,
        ])->assertFailed();
    }
}
