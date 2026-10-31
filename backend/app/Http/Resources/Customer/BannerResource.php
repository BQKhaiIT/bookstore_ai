<?php

namespace App\Http\Resources\Customer;

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
            'mobile_image_url' => $this->mobile_image_url,
            'cta_text' => $this->cta_text,
            'link_url' => $this->link_url,
            'position' => $this->position,
            'sort_order' => $this->sort_order,
        ];
    }
}
