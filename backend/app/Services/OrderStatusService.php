<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Models\Book;
use App\Models\Order;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;

class OrderStatusService
{
    /** @var array<string, list<string>> */
    private const TRANSITIONS = [
        'pending' => ['confirmed', 'cancelled'],
        'confirmed' => ['packing', 'cancelled'],
        'packing' => ['shipping', 'cancelled'],
        'shipping' => ['delivered', 'cancelled'],
        'delivered' => [],
        'cancelled' => [],
    ];

    public function transition(Order $order, OrderStatus $target): Order
    {
        return DB::transaction(function () use ($order, $target) {
            $order = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            $current = $order->status;

            if ($current === $target) {
                return $order->load(['items.book', 'user', 'payment']);
            }

            if (! $this->canTransition($current, $target)) {
                throw new HttpResponseException(response()->json([
                    'message' => 'Không thể chuyển trạng thái đơn hàng.',
                    'code' => 'ORDER_STATUS_INVALID',
                    'errors' => (object) [],
                ], 409));
            }

            if ($target === OrderStatus::Confirmed) {
                $this->decrementStock($order);
            }

            if ($target === OrderStatus::Cancelled && $this->stockWasCommitted($current)) {
                $this->restoreStock($order);
            }

            $order->status = $target;

            if ($target === OrderStatus::Cancelled) {
                $order->cancelled_at = now();
            }

            $order->save();

            return $order->fresh(['items.book', 'user', 'payment']);
        });
    }

    public function canTransition(OrderStatus $from, OrderStatus $to): bool
    {
        $allowed = self::TRANSITIONS[$from->value] ?? [];

        return in_array($to->value, $allowed, true);
    }

    private function stockWasCommitted(OrderStatus $status): bool
    {
        return in_array($status, [
            OrderStatus::Confirmed,
            OrderStatus::Packing,
            OrderStatus::Shipping,
            OrderStatus::Delivered,
        ], true);
    }

    private function decrementStock(Order $order): void
    {
        $order->loadMissing('items');

        foreach ($order->items as $item) {
            $book = Book::query()->whereKey($item->book_id)->lockForUpdate()->firstOrFail();

            if ($book->stock_quantity < $item->quantity) {
                throw new HttpResponseException(response()->json([
                    'message' => 'Không đủ tồn kho cho sách: '.$book->title,
                    'code' => 'INSUFFICIENT_STOCK',
                    'errors' => (object) [],
                ], 409));
            }

            $book->decrement('stock_quantity', $item->quantity);
        }
    }

    private function restoreStock(Order $order): void
    {
        $order->loadMissing('items');

        foreach ($order->items as $item) {
            Book::query()->whereKey($item->book_id)->lockForUpdate()->increment('stock_quantity', $item->quantity);
        }
    }
}
