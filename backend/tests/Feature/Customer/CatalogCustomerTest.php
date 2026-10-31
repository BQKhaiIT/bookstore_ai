<?php

namespace Tests\Feature\Customer;

use App\Enums\BookStatus;
use App\Models\Book;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogCustomerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_list_published_books_only(): void
    {
        Book::factory()->create(['status' => BookStatus::Published, 'title' => 'Public Book']);
        Book::factory()->create(['status' => BookStatus::Draft, 'title' => 'Hidden Book']);

        $this->getJson('/api/v1/books')
            ->assertOk()
            ->assertJsonPath('data.0.title', 'Public Book')
            ->assertJsonCount(1, 'data');
    }
}
