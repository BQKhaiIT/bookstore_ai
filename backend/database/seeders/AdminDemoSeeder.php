<?php

namespace Database\Seeders;

use App\Enums\BookStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\ReviewStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Author;
use App\Models\Book;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Publisher;
use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminDemoSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()->firstOrCreate(
            ['email' => 'admin@bookstore.test'],
            [
                'name' => 'Admin Demo',
                'password' => Hash::make('Password1'),
                'role' => UserRole::Admin,
                'status' => UserStatus::Active,
                'email_verified_at' => now(),
            ]
        );

        if ($admin->email_verified_at === null) {
            $admin->email_verified_at = now();
            $admin->save();
        }

        $customerActive = User::query()->firstOrCreate(
            ['email' => 'customer@bookstore.test'],
            [
                'name' => 'Khách Active',
                'password' => Hash::make('Password1'),
                'role' => UserRole::Customer,
                'status' => UserStatus::Active,
                'email_verified_at' => now(),
            ]
        );

        User::query()->firstOrCreate(
            ['email' => 'blocked@bookstore.test'],
            [
                'name' => 'Khách Blocked',
                'password' => Hash::make('Password1'),
                'role' => UserRole::Customer,
                'status' => UserStatus::Blocked,
                'email_verified_at' => now(),
            ]
        );

        $publishers = collect([
            'NXB Trẻ',
            'NXB Kim Đồng',
            'Alpha Books',
        ])->map(fn (string $name) => Publisher::query()->firstOrCreate(['name' => $name]));

        $categories = collect([
            'Văn học',
            'Kinh tế',
            'Khoa học',
            'Thiếu nhi',
        ])->map(function (string $name) {
            return Category::query()->firstOrCreate(
                ['slug' => \Illuminate\Support\Str::slug($name)],
                ['name' => $name]
            );
        });

        $authors = collect([
            ['name' => 'Nguyễn Nhật Ánh', 'biography' => 'Nhà văn Việt Nam.'],
            ['name' => 'Haruki Murakami', 'biography' => 'Nhà văn Nhật Bản.'],
            ['name' => 'Yuval Noah Harari', 'biography' => 'Tác giả Sapiens.'],
        ])->map(fn (array $data) => Author::query()->firstOrCreate(['name' => $data['name']], $data));

        if (Book::query()->count() < 8) {
            Book::factory(8)->create()->each(function (Book $book) use ($authors, $categories, $publishers): void {
                $book->publisher_id = $publishers->random()->id;
                $book->status = fake()->randomElement([BookStatus::Published, BookStatus::Draft]);
                $book->save();
                $book->authors()->sync($authors->random(rand(1, 2))->pluck('id'));
                $book->categories()->sync($categories->random(rand(1, 2))->pluck('id'));
            });
        }

        if (Order::query()->count() < 6) {
            $books = Book::query()->where('status', BookStatus::Published)->limit(5)->get();

            foreach ([OrderStatus::Pending, OrderStatus::Confirmed, OrderStatus::Shipping, OrderStatus::Delivered, OrderStatus::Cancelled] as $status) {
                $order = Order::factory()->create([
                    'user_id' => $customerActive->id,
                    'status' => $status,
                ]);

                $book = $books->random();
                OrderItem::query()->create([
                    'order_id' => $order->id,
                    'book_id' => $book->id,
                    'book_title' => $book->title,
                    'quantity' => 1,
                    'unit_price' => $book->price,
                    'subtotal' => $book->price,
                ]);

                $paymentStatus = $status === OrderStatus::Delivered
                    ? PaymentStatus::Paid
                    : PaymentStatus::Pending;

                Payment::query()->firstOrCreate(
                    ['order_id' => $order->id],
                    [
                        'method' => PaymentMethod::Cod,
                        'amount' => $order->total_amount,
                        'status' => $paymentStatus,
                        'paid_at' => $paymentStatus === PaymentStatus::Paid ? now() : null,
                    ]
                );
            }
        }

        $booksForReviews = Book::query()->limit(3)->get();
        $reviewStatuses = [ReviewStatus::Pending, ReviewStatus::Approved, ReviewStatus::Rejected];
        foreach ($reviewStatuses as $index => $status) {
            $book = $booksForReviews[$index] ?? $booksForReviews->first();
            if ($book === null) {
                break;
            }
            Review::query()->firstOrCreate(
                [
                    'user_id' => $customerActive->id,
                    'book_id' => $book->id,
                ],
                [
                    'rating' => fake()->numberBetween(3, 5),
                    'comment' => fake()->optional()->sentence(),
                    'status' => $status,
                ]
            );
        }
    }
}
