<?php

namespace Tests\Feature\Admin;

use App\Enums\AdminPermission;
use App\Enums\BannerType;
use App\Models\Banner;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class BannerAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_with_banners_permission_can_crud(): void
    {
        $admin = User::factory()->withPermissions([AdminPermission::Banners->value])->create();
        $this->actingAs($admin);

        $create = $this->postJson('/api/v1/admin/banners', [
            'type' => BannerType::Hero->value,
            'title' => 'Hero mùa hè',
            'description' => 'Mô tả hero',
            'cta_text' => 'Khám phá',
            'link_url' => '/books',
            'position' => 'homepage',
            'sort_order' => 1,
            'is_active' => true,
        ])->assertCreated()
            ->assertJsonPath('data.title', 'Hero mùa hè');

        $id = $create->json('data.id');

        $this->getJson('/api/v1/admin/banners/'.$id)
            ->assertOk()
            ->assertJsonPath('data.type', 'hero');

        $this->patchJson('/api/v1/admin/banners/'.$id, [
            'title' => 'Hero cập nhật',
            'is_active' => false,
        ])->assertOk()
            ->assertJsonPath('data.title', 'Hero cập nhật');

        $this->getJson('/api/v1/admin/banners?type=hero')
            ->assertOk()
            ->assertJsonPath('meta.total', 1);

        $this->deleteJson('/api/v1/admin/banners/'.$id)->assertNoContent();
    }

    public function test_admin_without_banners_permission_forbidden(): void
    {
        $admin = User::factory()->withPermissions([AdminPermission::Dashboard->value])->create();
        $this->actingAs($admin);

        $this->getJson('/api/v1/admin/banners')->assertForbidden();
    }

    public function test_banner_link_url_validation_blocks_javascript(): void
    {
        $this->actingAsAdmin();

        $this->postJson('/api/v1/admin/banners', [
            'type' => BannerType::Banner->value,
            'title' => 'X',
            'link_url' => 'javascript:alert(1)',
        ])->assertUnprocessable();
    }

    public function test_banner_link_url_allows_cloudinary_delivery_host(): void
    {
        $this->actingAsAdmin();

        $this->postJson('/api/v1/admin/banners', [
            'type' => BannerType::Hero->value,
            'title' => 'Hero',
            'link_url' => 'https://res.cloudinary.com/demo/image/upload/v1/bookstore/banners/hero.jpg',
        ])->assertCreated();
    }

    public function test_admin_can_upload_banner_image(): void
    {
        $this->actingAsAdmin();
        $banner = Banner::factory()->create();

        $this->post('/api/v1/admin/banners/'.$banner->id.'/image', [
            'variant' => 'desktop',
            'image' => UploadedFile::fake()->create('hero.jpg', 100, 'image/jpeg'),
        ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.image_public_id', 'bookstore/banners/uploaded');

        $this->assertCount(1, $this->fakeCloudinary->uploadedFiles);
    }

    public function test_banner_only_admin_can_list_cloudinary(): void
    {
        $admin = User::factory()->withPermissions([AdminPermission::Banners->value])->create();
        $this->actingAs($admin);

        $this->getJson('/api/v1/admin/cloudinary/images?per_page=5')->assertOk();
    }
}
