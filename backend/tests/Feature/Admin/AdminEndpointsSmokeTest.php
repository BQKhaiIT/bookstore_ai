<?php

namespace Tests\Feature\Admin;

use App\Models\Author;
use App\Models\Book;
use App\Models\Category;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Publisher;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminEndpointsSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_admin_endpoints_respond_for_verified_admin(): void
    {
        $this->actingAsAdmin();

        $publisher = Publisher::factory()->create();
        $author = Author::factory()->create();
        $category = Category::factory()->create();
        $book = Book::factory()->create(['publisher_id' => $publisher->id]);
        $book->authors()->attach($author);
        $book->categories()->attach($category);
        $order = Order::factory()->create();
        $payment = Payment::factory()->create(['order_id' => $order->id, 'amount' => $order->total_amount]);
        $customer = User::factory()->create();
        $review = Review::factory()->create();

        $this->getJson('/api/v1/admin/dashboard')->assertOk();
        $this->getJson('/api/v1/admin/books')->assertOk();
        $this->postJson('/api/v1/admin/books', ['title' => 'Smoke Book', 'price' => 1000])->assertCreated();
        $this->getJson('/api/v1/admin/books/'.$book->id)->assertOk();
        $this->patchJson('/api/v1/admin/books/'.$book->id, ['price' => 1100])->assertOk();

        $this->getJson('/api/v1/admin/categories')->assertOk();
        $this->postJson('/api/v1/admin/categories', ['name' => 'Smoke Cat'])->assertCreated();
        $this->getJson('/api/v1/admin/categories/'.$category->id)->assertOk();
        $this->patchJson('/api/v1/admin/categories/'.$category->id, ['name' => 'Cat2'])->assertOk();

        $this->getJson('/api/v1/admin/authors')->assertOk();
        $this->postJson('/api/v1/admin/authors', ['name' => 'Smoke Author'])->assertCreated();
        $this->getJson('/api/v1/admin/authors/'.$author->id)->assertOk();
        $this->patchJson('/api/v1/admin/authors/'.$author->id, ['name' => 'Author2'])->assertOk();

        $this->getJson('/api/v1/admin/publishers')->assertOk();
        $this->postJson('/api/v1/admin/publishers', ['name' => 'Smoke Pub'])->assertCreated();
        $this->getJson('/api/v1/admin/publishers/'.$publisher->id)->assertOk();
        $this->patchJson('/api/v1/admin/publishers/'.$publisher->id, ['name' => 'Pub2'])->assertOk();

        $this->getJson('/api/v1/admin/orders')->assertOk();
        $this->getJson('/api/v1/admin/orders/'.$order->id)->assertOk();
        $this->patchJson('/api/v1/admin/orders/'.$order->id.'/status', ['status' => 'cancelled'])->assertOk();

        $this->getJson('/api/v1/admin/payments')->assertOk();
        $this->postJson('/api/v1/admin/payments/'.$payment->id.'/confirm-cod')->assertOk();

        $this->getJson('/api/v1/admin/users')->assertOk();
        $this->patchJson('/api/v1/admin/users/'.$customer->id.'/status', ['status' => 'blocked'])->assertOk();

        $this->getJson('/api/v1/admin/reviews')->assertOk();
        $this->patchJson('/api/v1/admin/reviews/'.$review->id, ['status' => 'approved'])->assertOk();

        $this->getJson('/api/v1/admin/probe')->assertOk();
    }
}
