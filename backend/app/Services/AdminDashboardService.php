<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Models\Book;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Support\DemoCommerceMarker;

class AdminDashboardService
{
    /**
     * @return array<string, int|float>
     */
    public function stats(): array
    {
        $revenueBase = Payment::query()
            ->where('status', PaymentStatus::Paid)
            ->where(function ($query) {
                $query->whereNull('transaction_reference')
                    ->orWhere('transaction_reference', 'not like', DemoCommerceMarker::PAYMENT_REFERENCE_PREFIX.'%');
            })
            ->where(function ($query) {
                $query->whereNull('environment')
                    ->orWhere('environment', '!=', 'sandbox');
            });

        $revenue = (int) (clone $revenueBase)->sum('amount');

        $sandboxRevenue = (int) Payment::query()
            ->where('status', PaymentStatus::Paid)
            ->where('environment', 'sandbox')
            ->sum('amount');

        return [
            'revenue' => $revenue,
            'sandbox_revenue' => $sandboxRevenue,
            'orders_count' => Order::query()->count(),
            'books_count' => Book::query()->count(),
            'customers_count' => User::query()->where('role', UserRole::Customer)->count(),
            'pending_orders_count' => Order::query()->where('status', OrderStatus::Pending)->count(),
        ];
    }
}
