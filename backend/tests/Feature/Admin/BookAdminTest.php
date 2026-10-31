<?php

namespace Tests\Feature\Admin;

use App\Enums\BookStatus;
use App\Models\Author;
use App\Models\Book;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
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
        $this->actingAsAdmin();
        $book = Book::factory()->create();

        $this->postJson('/api/v1/admin/books/'.$book->id.'/cover', [
            'cover' => UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf'),
        ])->assertUnprocessable();

        $this->post('/api/v1/admin/books/'.$book->id.'/cover', [
            'cover' => UploadedFile::fake()->create('cover.jpg', 100, 'image/jpeg'),
        ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.id', $book->id)
            ->assertJsonPath('data.cover_url', 'https://res.cloudinary.com/demo/image/upload/v1/bookstore/covers/uploaded.jpg')
            ->assertJsonPath('data.cover_public_id', 'bookstore/covers/uploaded');

        $this->assertCount(1, $this->fakeCloudinary->uploadedFiles);
    }

    public function test_admin_can_assign_existing_cloudinary_cover(): void
    {
        $this->actingAsAdmin();
        $book = Book::factory()->create();

        $this->putJson('/api/v1/admin/books/'.$book->id.'/cover', [
            'public_id' => 'bookstore/covers/existing-one',
        ])->assertOk()
            ->assertJsonPath('data.cover_public_id', 'bookstore/covers/existing-one');
    }

    public function test_assign_cover_rejects_unknown_public_id(): void
    {
        $this->actingAsAdmin();
        $book = Book::factory()->create();

        $this->putJson('/api/v1/admin/books/'.$book->id.'/cover', [
            'public_id' => 'missing/asset',
        ])->assertUnprocessable()
            ->assertJsonPath('code', 'CLOUDINARY_ASSET_NOT_FOUND');
    }

    public function test_admin_can_remove_cover_without_deleting_cloud_asset(): void
    {
        $this->actingAsAdmin();
        $book = Book::factory()->create([
            'cover_url' => 'https://res.cloudinary.com/demo/image/upload/v1/bookstore/covers/existing-one.jpg',
            'cover_public_id' => 'bookstore/covers/existing-one',
            'cover_asset_id' => 'asset-existing-one',
        ]);

        $this->deleteJson('/api/v1/admin/books/'.$book->id.'/cover')
            ->assertOk()
            ->assertJsonPath('data.cover_url', null)
            ->assertJsonPath('data.cover_public_id', null);

        $book->refresh();
        $this->assertNull($book->cover_url);
    }

    public function test_admin_can_list_cloudinary_images(): void
    {
        $this->actingAsAdmin();

        $this->getJson('/api/v1/admin/cloudinary/images?per_page=10')
            ->assertOk()
            ->assertJsonPath('data.0.public_id', 'bookstore/covers/existing-one')
            ->assertJsonStructure(['data', 'meta' => ['next_cursor', 'has_more', 'search']]);
    }

    public function test_admin_can_search_cloudinary_images_by_partial_name(): void
    {
        $this->actingAsAdmin();

        $this->getJson('/api/v1/admin/cloudinary/images?search=vineland')
            ->assertOk()
            ->assertJsonPath('meta.search', 'vineland')
            ->assertJsonPath('data.0.public_id', 'Vineland');

        $this->getJson('/api/v1/admin/cloudinary/images?search=existing')
            ->assertOk()
            ->assertJsonPath('data.0.public_id', 'bookstore/covers/existing-one');
    }

    public function test_cloudinary_search_returns_empty_when_no_match(): void
    {
        $this->actingAsAdmin();

        $this->getJson('/api/v1/admin/cloudinary/images?search=zzzz-no-match-zzzz')
            ->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.has_more', false);
    }

    public function test_cloudinary_search_pagination_uses_cursor(): void
    {
        $this->actingAsAdmin();

        $this->getJson('/api/v1/admin/cloudinary/images?per_page=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.has_more', true);

        $this->getJson('/api/v1/admin/cloudinary/images?per_page=1&cursor=page-2')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->getJson('/api/v1/admin/cloudinary/images?search=vineland&per_page=1')
            ->assertOk()
            ->assertJsonPath('data.0.public_id', 'Vineland')
            ->assertJsonPath('meta.search', 'vineland')
            ->assertJsonPath('meta.has_more', false);
    }

    public function test_cloudinary_upload_failure_returns_502(): void
    {
        $this->actingAsAdmin();
        $this->fakeCloudinary->uploadShouldFail = true;
        $book = Book::factory()->create();

        $this->post('/api/v1/admin/books/'.$book->id.'/cover', [
            'cover' => UploadedFile::fake()->create('cover.jpg', 100, 'image/jpeg'),
        ], ['Accept' => 'application/json'])
            ->assertStatus(502)
            ->assertJsonPath('code', 'CLOUDINARY_UPSTREAM_ERROR');
    }

    public function test_non_books_admin_cannot_upload_cover(): void
    {
        $admin = User::factory()->admin()->withPermissions(['dashboard'])->create();
        $this->actingAs($admin);
        $book = Book::factory()->create();

        $this->postJson('/api/v1/admin/books/'.$book->id.'/cover', [
            'cover' => UploadedFile::fake()->create('cover.jpg', 100, 'image/jpeg'),
        ])->assertForbidden();
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
