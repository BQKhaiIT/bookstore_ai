<?php

namespace Tests\Feature\Admin;

use App\Models\Author;
use App\Models\Book;
use App\Models\Category;
use App\Models\Publisher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_category_crud_and_in_use_guard(): void
    {
        $this->actingAsAdmin();
        $category = Category::factory()->create(['name' => 'Khoa học']);
        Book::factory()->create()->categories()->attach($category);

        $this->getJson('/api/v1/admin/categories?search=Khoa')->assertOk();
        $this->patchJson('/api/v1/admin/categories/'.$category->id, ['name' => 'STEM'])->assertOk();
        $this->deleteJson('/api/v1/admin/categories/'.$category->id)
            ->assertStatus(409)
            ->assertJsonPath('code', 'CATEGORY_IN_USE');
    }

    public function test_author_and_publisher_crud(): void
    {
        $this->actingAsAdmin();

        $authorResponse = $this->postJson('/api/v1/admin/authors', ['name' => 'Nguyễn A'])->assertCreated();
        $authorId = $authorResponse->json('data.id');

        $publisherResponse = $this->postJson('/api/v1/admin/publishers', ['name' => 'NXB Trẻ'])->assertCreated();
        $publisherId = $publisherResponse->json('data.id');

        $this->getJson('/api/v1/admin/authors/'.$authorId)->assertOk();
        $this->getJson('/api/v1/admin/publishers/'.$publisherId)->assertOk();

        Author::factory()->create()->books()->attach(Book::factory()->create());
        $this->deleteJson('/api/v1/admin/authors/'.$authorId)->assertNoContent();

        Publisher::query()->findOrFail($publisherId);
        $this->deleteJson('/api/v1/admin/publishers/'.$publisherId)->assertNoContent();
    }
}
