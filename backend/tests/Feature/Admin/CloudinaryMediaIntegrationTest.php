<?php

namespace Tests\Feature\Admin;

use App\Contracts\CloudinaryImageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CloudinaryMediaIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_live_cloudinary_list_returns_images_when_configured(): void
    {
        if (! filter_var(env('CLOUDINARY_INTEGRATION'), FILTER_VALIDATE_BOOL)) {
            $this->markTestSkipped('Set CLOUDINARY_INTEGRATION=true to run live Cloudinary tests.');
        }

        $this->app->forgetInstance(CloudinaryImageService::class);
        $this->app->singleton(CloudinaryImageService::class, \App\Services\CloudinaryImageService::class);

        $this->actingAsAdmin();

        $response = $this->getJson('/api/v1/admin/cloudinary/images?per_page=24');

        $response->assertOk()
            ->assertJsonStructure(['data', 'meta' => ['next_cursor', 'has_more']]);

        $this->assertNotEmpty($response->json('data'));
        $this->assertIsString($response->json('data.0.public_id'));
        $this->assertIsString($response->json('data.0.secure_url'));
    }
}
