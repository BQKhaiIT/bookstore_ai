<?php

namespace Tests\Feature\Admin;

use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_counts_and_revenue_from_paid_payments_only(): void
    {
        $this->actingAsAdmin();

        $paidOrder = Order::factory()->create();
        Payment::factory()->paid()->create(['order_id' => $paidOrder->id, 'amount' => 150000]);
        $pendingOrder = Order::factory()->create();
        Payment::factory()->create([
            'order_id' => $pendingOrder->id,
            'amount' => 999999,
            'status' => PaymentStatus::Pending,
        ]);

        $response = $this->getJson('/api/v1/admin/dashboard')->assertOk();

        $response->assertJsonPath('data.revenue', 150000);
        $this->assertSame(2, $response->json('data.orders_count'));
        $this->assertGreaterThanOrEqual(2, $response->json('data.customers_count'));
    }
}
