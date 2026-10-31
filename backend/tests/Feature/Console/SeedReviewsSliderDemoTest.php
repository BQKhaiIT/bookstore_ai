<?php

namespace Tests\Feature\Console;

use App\Enums\BookStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\ReviewStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Book;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Review;
use App\Models\User;
use App\Support\DemoCommerceMarker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class SeedReviewsSliderDemoTest extends TestCase
{
    use RefreshDatabase;

    public function test_seed_command_creates_demo_commerce_chain(): void
    {
        User::factory()->create([
            'email' => 'customer@bookstore.test',
            'role' => UserRole::Customer,
            'status' => UserStatus::Active,
        ]);

        Book::factory()->count(12)->create([
            'status' => BookStatus::Published,
            'cover_url' => 'https://res.cloudinary.com/demo/image/upload/v1/book.jpg',
        ]);

        $this->artisan('demo:seed-reviews-slider', ['--force' => true])
            ->assertSuccessful();

        $customer = User::query()->where('email', 'customer@bookstore.test')->firstOrFail();

        $this->assertSame(
            10,
            Review::query()
                ->where('user_id', $customer->id)
                ->where('status', ReviewStatus::Approved)
                ->count(),
        );

        $this->assertSame(
            10,
            Order::query()
                ->where('user_id', $customer->id)
                ->where('status', OrderStatus::Delivered)
                ->where('idempotency_key', 'like', DemoCommerceMarker::IDEMPOTENCY_PREFIX.'%')
                ->count(),
        );

        $this->assertSame(
            10,
            Payment::query()
                ->where('status', PaymentStatus::Paid)
                ->where('transaction_reference', 'like', DemoCommerceMarker::PAYMENT_REFERENCE_PREFIX.'%')
                ->count(),
        );
    }

    public function test_popular_sort_orders_by_approved_reviews(): void
    {
        User::factory()->create(['email' => 'customer@bookstore.test']);

        Book::factory()->count(12)->create([
            'status' => BookStatus::Published,
            'cover_url' => 'https://res.cloudinary.com/demo/image/upload/v1/book.jpg',
        ]);

        Artisan::call('demo:seed-reviews-slider', ['--force' => true]);

        $response = $this->getJson('/api/v1/books?sort=popular&per_page=8')->assertOk();

        $ids = collect($response->json('data'))->pluck('id');
        $this->assertGreaterThanOrEqual(8, $ids->count());
    }

    public function test_dashboard_revenue_excludes_demo_payments(): void
    {
        $this->actingAsAdmin();

        $realOrder = Order::factory()->create();
        Payment::factory()->paid()->create(['order_id' => $realOrder->id, 'amount' => 100000]);

        $demoOrder = Order::factory()->create([
            'idempotency_key' => DemoCommerceMarker::idempotencyKeyForBook(999),
        ]);
        Payment::factory()->paid()->create([
            'order_id' => $demoOrder->id,
            'amount' => 500000,
            'transaction_reference' => DemoCommerceMarker::PAYMENT_REFERENCE_PREFIX.'order:'.$demoOrder->id,
        ]);

        $response = $this->getJson('/api/v1/admin/dashboard')->assertOk();

        $response->assertJsonPath('data.revenue', 100000);
    }
}
