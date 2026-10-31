<?php

namespace App\Http\Resources\Admin;

use App\Models\Banner;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Banner */
class BannerResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'title' => $this->title,
            'description' => $this->description,
            'image_url' => $this->image_url,
            'image_public_id' => $this->image_public_id,
            'mobile_image_url' => $this->mobile_image_url,
            'mobile_image_public_id' => $this->mobile_image_public_id,
            'cta_text' => $this->cta_text,
            'link_url' => $this->link_url,
            'position' => $this->position,
            'sort_order' => $this->sort_order,
            'is_active' => $this->is_active,
            'starts_at' => $this->starts_at?->toIso8601String(),
            'ends_at' => $this->ends_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
