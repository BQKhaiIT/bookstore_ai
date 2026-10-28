<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Models\Book;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;

class AdminDashboardService
{
    /**
     * @return array<string, int|float>
     */
    public function stats(): array
    {
        $revenue = (int) Payment::query()
            ->where('status', PaymentStatus::Paid)
            ->sum('amount');

        return [
            'revenue' => $revenue,
            'orders_count' => Order::query()->count(),
            'books_count' => Book::query()->count(),
            'customers_count' => User::query()->where('role', UserRole::Customer)->count(),
            'pending_orders_count' => Order::query()->where('status', OrderStatus::Pending)->count(),
        ];
    }
}
