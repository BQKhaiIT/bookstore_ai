<?php

namespace Tests\Feature\Admin;

use App\Enums\BookStatus;
use App\Models\Author;
use App\Models\Book;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BookAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_and_list_books(): void
    {
        $this->actingAsAdmin();
        $author = Author::factory()->create();
        $category = Category::factory()->create();

        $this->postJson('/api/v1/admin/books', [
            'title' => 'Sách Test',
            'price' => 120000,
            'stock_quantity' => 5,
            'author_ids' => [$author->id],
            'category_ids' => [$category->id],
        ])->assertCreated()
            ->assertJsonPath('data.title', 'Sách Test');

        $this->getJson('/api/v1/admin/books?search=Sách')
            ->assertOk()
            ->assertJsonPath('meta.total', 1);
    }

    public function test_book_validation_errors(): void
    {
        $this->actingAsAdmin();

        $this->postJson('/api/v1/admin/books', [])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'VALIDATION_ERROR');
    }

    public function test_admin_can_upload_and_reject_invalid_cover(): void
    {
        Storage::fake('public');
        $this->actingAsAdmin();
        $book = Book::factory()->create();

        $this->postJson('/api/v1/admin/books/'.$book->id.'/cover', [
            'cover' => UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf'),
        ])->assertUnprocessable();

        $this->post('/api/v1/admin/books/'.$book->id.'/cover', [
            'cover' => UploadedFile::fake()->create('cover.jpg', 100, 'image/jpeg'),
        ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.id', $book->id);
    }

    public function test_delete_book_with_orders_archives_instead(): void
    {
        $this->actingAsAdmin();
        $book = Book::factory()->create();
        OrderItem::query()->create([
            'order_id' => Order::factory()->create()->id,
            'book_id' => $book->id,
            'book_title' => $book->title,
            'quantity' => 1,
            'unit_price' => $book->price,
            'subtotal' => $book->price,
        ]);

        $this->deleteJson('/api/v1/admin/books/'.$book->id)
            ->assertOk()
            ->assertJsonPath('data.status', BookStatus::Archived->value);
    }
}
