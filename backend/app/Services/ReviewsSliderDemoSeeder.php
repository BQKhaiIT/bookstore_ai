<?php

namespace App\Services;

use App\Enums\BookStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\ReviewStatus;
use App\Models\Book;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Review;
use App\Models\User;
use App\Support\DemoCommerceMarker;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ReviewsSliderDemoSeeder
{
    public const TARGET_BOOK_COUNT = 10;

    public const CUSTOMER_EMAIL = 'customer@bookstore.test';

    /**
     * @return array{
     *     books_selected: int,
     *     orders_created: int,
     *     payments_created: int,
     *     reviews_approved: int,
     *     book_ids: list<int>
     * }
     */
    public function seed(): array
    {
        $customer = User::query()->where('email', self::CUSTOMER_EMAIL)->firstOrFail();

        $books = $this->selectBooks($customer->id);

        $stats = [
            'books_selected' => 0,
            'orders_created' => 0,
            'payments_created' => 0,
            'reviews_approved' => 0,
            'book_ids' => [],
        ];

        DB::transaction(function () use ($customer, $books, &$stats): void {
            foreach ($books as $book) {
                if ($stats['books_selected'] >= self::TARGET_BOOK_COUNT) {
                    break;
                }

                $idempotencyKey = DemoCommerceMarker::idempotencyKeyForBook($book->id);

                $order = Order::query()->firstWhere([
                    'user_id' => $customer->id,
                    'idempotency_key' => $idempotencyKey,
                ]);

                if ($order === null) {
                    $order = $this->createDemoOrder($customer, $book, $idempotencyKey);
                    $stats['orders_created']++;
                }

                $payment = Payment::query()->firstWhere('order_id', $order->id);
                if ($payment === null) {
                    Payment::query()->create([
                        'order_id' => $order->id,
                        'method' => PaymentMethod::Cod,
                        'amount' => $order->total_amount,
                        'status' => PaymentStatus::Paid,
                        'transaction_reference' => DemoCommerceMarker::PAYMENT_REFERENCE_PREFIX.'order:'.$order->id,
                        'paid_at' => now(),
                    ]);
                    $stats['payments_created']++;
                } else {
                    $payment->fill([
                        'method' => PaymentMethod::Cod,
                        'amount' => $order->total_amount,
                        'status' => PaymentStatus::Paid,
                        'transaction_reference' => DemoCommerceMarker::PAYMENT_REFERENCE_PREFIX.'order:'.$order->id,
                        'paid_at' => $payment->paid_at ?? now(),
                    ])->save();
                }

                $review = Review::query()->firstWhere([
                    'user_id' => $customer->id,
                    'book_id' => $book->id,
                ]);

                if ($review === null) {
                    Review::query()->create([
                        'user_id' => $customer->id,
                        'book_id' => $book->id,
                        'rating' => random_int(4, 5),
                        'comment' => DemoCommerceMarker::REVIEW_COMMENT_PREFIX.fake()->sentence(),
                        'status' => ReviewStatus::Approved,
                    ]);
                    $stats['reviews_approved']++;
                } elseif ($review->status !== ReviewStatus::Approved) {
                    $review->fill([
                        'rating' => max(4, min(5, (int) $review->rating)),
                        'status' => ReviewStatus::Approved,
                    ])->save();
                }

                $stats['books_selected']++;
                $stats['book_ids'][] = $book->id;
            }
        });

        return $stats;
    }

    /**
     * @return Collection<int, Book>
     */
    private function selectBooks(int $customerUserId): Collection
    {
        unset($customerUserId);

        $withCover = Book::query()
            ->where('status', BookStatus::Published)
            ->whereNotNull('cover_url')
            ->where('cover_url', '!=', '')
            ->whereRaw('LOWER(cover_url) LIKE ?', ['%cloudinary%'])
            ->inRandomOrder()
            ->limit(self::TARGET_BOOK_COUNT * 3)
            ->get();

        if ($withCover->count() >= self::TARGET_BOOK_COUNT) {
            return $withCover->take(self::TARGET_BOOK_COUNT);
        }

        $fallback = Book::query()
            ->where('status', BookStatus::Published)
            ->whereNotIn('id', $withCover->pluck('id'))
            ->inRandomOrder()
            ->limit(self::TARGET_BOOK_COUNT * 2)
            ->get();

        return $withCover->concat($fallback)->unique('id')->take(self::TARGET_BOOK_COUNT);
    }

    private function createDemoOrder(User $customer, Book $book, string $idempotencyKey): Order
    {
        $quantity = 1;
        $subtotal = $book->price * $quantity;
        $shippingFee = 30_000;
        $total = $subtotal + $shippingFee;

        $order = Order::query()->create([
            'user_id' => $customer->id,
            'order_code' => 'ORD-DEMO-'.Str::upper(Str::random(8)),
            'idempotency_key' => $idempotencyKey,
            'idempotency_request_hash' => hash('sha256', $idempotencyKey),
            'status' => OrderStatus::Delivered,
            'subtotal_amount' => $subtotal,
            'shipping_fee' => $shippingFee,
            'total_amount' => $total,
            'shipping_address_snapshot' => [
                'recipient_name' => $customer->name,
                'phone' => '0900000000',
                'province' => 'Hà Nội',
                'district' => 'Demo',
                'ward' => 'Demo',
                'address_line' => 'Demo local seed — không giao hàng thật',
            ],
            'customer_note' => DemoCommerceMarker::ORDER_NOTE,
        ]);

        OrderItem::query()->create([
            'order_id' => $order->id,
            'book_id' => $book->id,
            'book_title' => $book->title,
            'quantity' => $quantity,
            'unit_price' => $book->price,
            'subtotal' => $subtotal,
        ]);

        return $order;
    }
}
