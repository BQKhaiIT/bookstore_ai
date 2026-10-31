<?php

namespace App\Services;

use App\Enums\BookStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Models\UserAddress;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CheckoutService
{
    public function __construct(private CartService $cartService) {}

    /**
     * @return array{subtotal_amount: int, shipping_fee: int, total_amount: int, items: list<array<string, mixed>>}
     */
    public function preview(User $user, ?UserAddress $address = null): array
    {
        $cart = $this->cartService->loadCart($this->cartService->forUser($user));

        if ($cart->items->isEmpty()) {
            throw new HttpResponseException(response()->json([
                'message' => 'Giỏ hàng trống.',
                'code' => 'CART_EMPTY',
                'errors' => (object) [],
            ], 422));
        }

        $lines = [];
        $subtotal = 0;

        foreach ($cart->items as $item) {
            $book = $item->book;
            if (! $book || $book->status !== BookStatus::Published) {
                throw new HttpResponseException(response()->json([
                    'message' => 'Một số sách trong giỏ không còn khả dụng.',
                    'code' => 'BOOK_UNAVAILABLE',
                    'errors' => (object) [],
                ], 409));
            }

            if ($item->quantity > $book->stock_quantity) {
                throw new HttpResponseException(response()->json([
                    'message' => 'Không đủ tồn kho cho: '.$book->title,
                    'code' => 'INSUFFICIENT_STOCK',
                    'errors' => (object) [],
                ], 409));
            }

            $lineSubtotal = $book->price * $item->quantity;
            $subtotal += $lineSubtotal;
            $lines[] = [
                'book_id' => $book->id,
                'book_title' => $book->title,
                'quantity' => $item->quantity,
                'unit_price' => $book->price,
                'subtotal' => $lineSubtotal,
            ];
        }

        $shipping = (int) config('bookstore.shipping_fee_vnd', 30000);

        return [
            'subtotal_amount' => $subtotal,
            'shipping_fee' => $shipping,
            'total_amount' => $subtotal + $shipping,
            'items' => $lines,
        ];
    }

    public function placeOrder(
        User $user,
        UserAddress $address,
        string $idempotencyKey,
        ?string $customerNote = null,
        PaymentMethod $paymentMethod = PaymentMethod::Cod,
    ): Order {
        $requestHash = hash('sha256', json_encode([
            'address_id' => $address->id,
            'note' => $customerNote,
            'payment_method' => $paymentMethod->value,
        ], JSON_THROW_ON_ERROR));

        $existing = Order::query()
            ->where('user_id', $user->id)
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        if ($existing) {
            if ($existing->idempotency_request_hash !== $requestHash) {
                throw new HttpResponseException(response()->json([
                    'message' => 'Idempotency-Key đã được sử dụng với payload khác.',
                    'code' => 'IDEMPOTENCY_CONFLICT',
                    'errors' => (object) [],
                ], 409));
            }

            return $existing->load(['items', 'payment']);
        }

        $preview = $this->preview($user, $address);

        return DB::transaction(function () use ($user, $address, $preview, $idempotencyKey, $requestHash, $customerNote, $paymentMethod) {
            $order = Order::query()->create([
                'user_id' => $user->id,
                'order_code' => 'ORD-'.Str::upper(Str::random(10)),
                'idempotency_key' => $idempotencyKey,
                'idempotency_request_hash' => $requestHash,
                'status' => OrderStatus::Pending,
                'subtotal_amount' => $preview['subtotal_amount'],
                'shipping_fee' => $preview['shipping_fee'],
                'total_amount' => $preview['total_amount'],
                'shipping_address_snapshot' => $address->toSnapshot(),
                'customer_note' => $customerNote,
            ]);

            foreach ($preview['items'] as $line) {
                $order->items()->create([
                    'book_id' => $line['book_id'],
                    'book_title' => $line['book_title'],
                    'quantity' => $line['quantity'],
                    'unit_price' => $line['unit_price'],
                    'subtotal' => $line['subtotal'],
                ]);
            }

            Payment::query()->create([
                'order_id' => $order->id,
                'method' => $paymentMethod,
                'provider' => $paymentMethod === PaymentMethod::SandboxOnline ? 'sepay' : null,
                'environment' => $paymentMethod === PaymentMethod::SandboxOnline ? 'sandbox' : null,
                'amount' => $order->total_amount,
                'status' => PaymentStatus::Pending,
            ]);

            $cart = $this->cartService->forUser($user);
            $cart->items()->delete();

            return $order->fresh(['items', 'payment']);
        });
    }
}
