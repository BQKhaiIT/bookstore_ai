<?php

namespace Tests\Feature\Admin;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Book;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderPaymentAdminTest extends TestCase
{
    use RefreshDatabase;

    private function orderWithItem(int $stock = 10, int $qty = 2): array
    {
        $book = Book::factory()->create(['stock_quantity' => $stock]);
        $order = Order::factory()->create(['status' => OrderStatus::Pending]);
        OrderItem::query()->create([
            'order_id' => $order->id,
            'book_id' => $book->id,
            'book_title' => $book->title,
            'quantity' => $qty,
            'unit_price' => $book->price,
            'subtotal' => $book->price * $qty,
        ]);

        return [$order, $book];
    }

    public function test_order_status_transitions_and_inventory(): void
    {
        $this->actingAsAdmin();
        [$order, $book] = $this->orderWithItem(stock: 5, qty: 2);

        $this->patchJson('/api/v1/admin/orders/'.$order->id.'/status', [
            'status' => OrderStatus::Confirmed->value,
        ])->assertOk();

        $this->assertSame(3, $book->fresh()->stock_quantity);

        $this->patchJson('/api/v1/admin/orders/'.$order->id.'/status', [
            'status' => OrderStatus::Delivered->value,
        ])->assertStatus(409);

        $this->patchJson('/api/v1/admin/orders/'.$order->id.'/status', [
            'status' => OrderStatus::Packing->value,
        ])->assertOk();

        $this->patchJson('/api/v1/admin/orders/'.$order->id.'/status', [
            'status' => OrderStatus::Cancelled->value,
        ])->assertOk();

        $this->assertSame(5, $book->fresh()->stock_quantity);
    }

    public function test_insufficient_stock_on_confirm(): void
    {
        $this->actingAsAdmin();
        [$order] = $this->orderWithItem(stock: 1, qty: 3);

        $this->patchJson('/api/v1/admin/orders/'.$order->id.'/status', [
            'status' => OrderStatus::Confirmed->value,
        ])->assertStatus(409)
            ->assertJsonPath('code', 'INSUFFICIENT_STOCK');
    }

    public function test_cod_payment_confirm_is_idempotent(): void
    {
        $this->actingAsAdmin();
        $order = Order::factory()->create();
        $payment = Payment::factory()->create([
            'order_id' => $order->id,
            'method' => PaymentMethod::Cod,
            'amount' => $order->total_amount,
            'status' => PaymentStatus::Pending,
        ]);

        $this->postJson('/api/v1/admin/payments/'.$payment->id.'/confirm-cod')
            ->assertOk()
            ->assertJsonPath('data.status', PaymentStatus::Paid->value);

        $this->postJson('/api/v1/admin/payments/'.$payment->id.'/confirm-cod')
            ->assertOk()
            ->assertJsonPath('data.status', PaymentStatus::Paid->value);

        $this->assertSame(1, Payment::query()->where('order_id', $order->id)->where('status', PaymentStatus::Paid)->count());
    }
}
