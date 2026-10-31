<?php

namespace Tests\Feature\Customer;

use App\Models\Banner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BannerCustomerTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_banners_only_returns_active_in_schedule(): void
    {
        Banner::factory()->hero()->create([
            'title' => 'Active hero',
            'is_active' => true,
            'sort_order' => 1,
            'image_url' => 'https://example.com/hero.jpg',
        ]);

        Banner::factory()->inactive()->create(['title' => 'Hidden']);

        Banner::factory()->create([
            'title' => 'Future',
            'is_active' => true,
            'starts_at' => now()->addDay(),
        ]);

        $response = $this->getJson('/api/v1/banners')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Active hero');

        $this->assertArrayNotHasKey('image_public_id', $response->json('data.0'));
    }
}
