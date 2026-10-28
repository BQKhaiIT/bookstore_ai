<?php

namespace Database\Factories;

use App\Enums\BookStatus;
use App\Models\Book;
use App\Models\Publisher;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Book> */
class BookFactory extends Factory
{
    protected $model = Book::class;

    public function definition(): array
    {
        $title = fake()->unique()->sentence(3);

        return [
            'publisher_id' => Publisher::factory(),
            'isbn' => fake()->optional()->isbn13(),
            'title' => $title,
            'slug' => Str::slug($title).'-'.fake()->unique()->numerify('####'),
            'description' => fake()->optional()->paragraph(),
            'price' => fake()->numberBetween(50000, 500000),
            'stock_quantity' => fake()->numberBetween(0, 100),
            'cover_url' => null,
            'status' => BookStatus::Published,
        ];
    }
}
