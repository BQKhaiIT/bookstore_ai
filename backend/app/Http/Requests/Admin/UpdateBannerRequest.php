<?php

namespace App\Http\Requests\Admin;

use App\Enums\BannerType;
use App\Rules\SafeBannerLinkUrl;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBannerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'type' => ['sometimes', 'string', Rule::in(BannerType::values())],
            'title' => ['sometimes', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:2000'],
            'cta_text' => ['nullable', 'string', 'max:80'],
            'link_url' => ['nullable', 'string', 'max:2048', new SafeBannerLinkUrl],
            'position' => ['sometimes', 'string', 'max:64'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['sometimes', 'boolean'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'image_url' => ['nullable', 'string', 'max:2048'],
            'image_public_id' => ['nullable', 'string', 'max:512'],
            'mobile_image_url' => ['nullable', 'string', 'max:2048'],
            'mobile_image_public_id' => ['nullable', 'string', 'max:512'],
        ];
    }
}
