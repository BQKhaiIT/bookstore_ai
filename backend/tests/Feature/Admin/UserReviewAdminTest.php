<?php

namespace Tests\Feature\Admin;

use App\Enums\ReviewStatus;
use App\Enums\UserStatus;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserReviewAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_search_users_and_block_customer(): void
    {
        $admin = $this->actingAsAdmin();
        $customer = User::factory()->create(['email' => 'cust@example.com']);

        $this->getJson('/api/v1/admin/users?search=cust@example.com')
            ->assertOk()
            ->assertJsonPath('data.0.id', $customer->id);

        $this->patchJson('/api/v1/admin/users/'.$customer->id.'/status', [
            'status' => UserStatus::Blocked->value,
        ])->assertOk()
            ->assertJsonPath('data.status', UserStatus::Blocked->value);

        $this->patchJson('/api/v1/admin/users/'.$admin->id.'/status', [
            'status' => UserStatus::Blocked->value,
        ])->assertForbidden()
            ->assertJsonPath('code', 'FORBIDDEN');
    }

    public function test_admin_can_moderate_reviews(): void
    {
        $this->actingAsAdmin();
        $review = Review::factory()->create();

        $this->getJson('/api/v1/admin/reviews?status=pending')->assertOk();

        $this->patchJson('/api/v1/admin/reviews/'.$review->id, [
            'status' => ReviewStatus::Approved->value,
        ])->assertOk()
            ->assertJsonPath('data.status', ReviewStatus::Approved->value);

        $this->patchJson('/api/v1/admin/reviews/'.$review->id, [
            'status' => ReviewStatus::Pending->value,
        ])->assertUnprocessable();
    }
}
