<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_me(): void
    {
        $this->getJson('/api/v1/me')->assertUnauthorized();
    }

    public function test_authenticated_user_can_access_me(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonMissing(['password', 'remember_token']);
    }

    public function test_customer_cannot_access_admin_probe(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/api/v1/admin/probe')
            ->assertForbidden()
            ->assertJsonPath('code', 'FORBIDDEN');
    }

    public function test_admin_can_access_admin_probe(): void
    {
        $user = User::factory()->admin()->create();

        $this->actingAs($user)
            ->getJson('/api/v1/admin/probe')
            ->assertOk();
    }

    public function test_blocked_user_cannot_access_protected_api(): void
    {
        $user = User::factory()->blocked()->create();
        $this->actingAs($user);

        $this->getJson('/api/v1/me')
            ->assertForbidden()
            ->assertJsonPath('code', 'ACCOUNT_BLOCKED');
    }

    public function test_unverified_user_cannot_access_checkout_probe(): void
    {
        $user = User::factory()->unverified()->create();
        $this->actingAs($user);

        $this->postJson('/api/v1/checkout-probe')
            ->assertForbidden();
    }

    public function test_verified_user_can_access_checkout_probe(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->postJson('/api/v1/checkout-probe')
            ->assertOk();
    }

    public function test_logout_invalidates_session(): void
    {
        $user = User::factory()->create([
            'email' => 'logout@example.com',
            'password' => 'Password1',
        ]);

        $this->postSpa('/login', [
            'email' => 'logout@example.com',
            'password' => 'Password1',
        ])->assertOk();

        $this->assertGreaterThan(0, DB::table('sessions')->where('user_id', $user->id)->count());

        $this->postSpa('/logout', [], false)->assertNoContent();

        $this->assertSame(0, DB::table('sessions')->where('user_id', $user->id)->count());
    }
}
