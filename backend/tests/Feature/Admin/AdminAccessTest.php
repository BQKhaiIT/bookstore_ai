<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_admin_dashboard(): void
    {
        $this->getJson('/api/v1/admin/dashboard')->assertUnauthorized();
    }

    public function test_customer_cannot_access_admin_dashboard(): void
    {
        $this->actingAs(User::factory()->create())
            ->getJson('/api/v1/admin/dashboard')
            ->assertForbidden();
    }

    public function test_blocked_admin_cannot_access_admin_dashboard(): void
    {
        $this->actingAs(User::factory()->admin()->blocked()->create())
            ->getJson('/api/v1/admin/dashboard')
            ->assertForbidden()
            ->assertJsonPath('code', 'ACCOUNT_BLOCKED');
    }

    public function test_unverified_admin_cannot_access_admin_dashboard(): void
    {
        $this->actingAs(User::factory()->admin()->unverified()->create())
            ->getJson('/api/v1/admin/dashboard')
            ->assertForbidden();
    }

    public function test_verified_admin_can_access_admin_dashboard(): void
    {
        $this->actingAsAdmin();

        $this->getJson('/api/v1/admin/dashboard')
            ->assertOk()
            ->assertJsonStructure(['data' => ['revenue', 'orders_count', 'books_count', 'customers_count']]);
    }
}
