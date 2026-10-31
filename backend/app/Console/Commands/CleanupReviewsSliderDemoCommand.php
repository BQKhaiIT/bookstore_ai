<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Review;
use App\Support\DemoCommerceMarker;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CleanupReviewsSliderDemoCommand extends Command
{
    protected $signature = 'demo:cleanup-reviews-slider
                            {--force : Cho phép chạy khi APP_ENV không phải local}';

    protected $description = 'Xóa dữ liệu demo reviews-slider (theo marker idempotency / payment / comment)';

    public function handle(): int
    {
        if (! app()->environment('local') && ! $this->option('force')) {
            $this->error('Chỉ chạy trong APP_ENV=local (hoặc dùng --force).');

            return self::FAILURE;
        }

        $deleted = DB::transaction(function (): array {
            $orderIds = Order::query()
                ->where('idempotency_key', 'like', DemoCommerceMarker::IDEMPOTENCY_PREFIX.'%')
                ->pluck('id');

            $payments = Payment::query()
                ->where(function ($q) use ($orderIds) {
                    $q->whereIn('order_id', $orderIds)
                        ->orWhere('transaction_reference', 'like', DemoCommerceMarker::PAYMENT_REFERENCE_PREFIX.'%');
                })
                ->delete();

            $items = OrderItem::query()->whereIn('order_id', $orderIds)->delete();
            $orders = Order::query()->whereIn('id', $orderIds)->delete();

            $reviews = Review::query()
                ->where('comment', 'like', DemoCommerceMarker::REVIEW_COMMENT_PREFIX.'%')
                ->delete();

            return ['orders' => $orders, 'items' => $items, 'payments' => $payments, 'reviews' => $reviews];
        });

        $this->info("Đã xóa: {$deleted['orders']} orders, {$deleted['payments']} payments, {$deleted['reviews']} reviews.");

        return self::SUCCESS;
    }
}
