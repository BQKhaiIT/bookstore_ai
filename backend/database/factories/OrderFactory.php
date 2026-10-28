<?php

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Order> */
class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        $subtotal = fake()->numberBetween(100000, 1000000);
        $shipping = 30000;

        return [
            'user_id' => User::factory(),
            'order_code' => 'ORD-'.Str::upper(Str::random(8)),
            'status' => OrderStatus::Pending,
            'subtotal_amount' => $subtotal,
            'shipping_fee' => $shipping,
            'total_amount' => $subtotal + $shipping,
            'shipping_address_snapshot' => [
                'recipient_name' => fake()->name(),
                'phone' => '0901234567',
                'province' => 'Hà Nội',
                'district' => 'Ba Đình',
                'ward' => 'Phúc Xá',
                'address_line' => '123 Test',
            ],
        ];
    }
}
