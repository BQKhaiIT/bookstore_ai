<?php

namespace Database\Factories;

use App\Enums\BannerType;
use App\Models\Banner;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Banner>
 */
class BannerFactory extends Factory
{
    protected $model = Banner::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => BannerType::Banner,
            'title' => fake()->sentence(3),
            'description' => fake()->optional()->paragraph(),
            'image_url' => null,
            'image_public_id' => null,
            'mobile_image_url' => null,
            'mobile_image_public_id' => null,
            'cta_text' => 'Xem thêm',
            'link_url' => '/books',
            'position' => 'homepage',
            'sort_order' => 0,
            'is_active' => true,
            'starts_at' => null,
            'ends_at' => null,
        ];
    }

    public function hero(): static
    {
        return $this->state(fn () => ['type' => BannerType::Hero]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
