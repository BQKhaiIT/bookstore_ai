<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePublisherRequest extends FormRequest
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
        $publisherId = $this->route('publisher')?->id ?? $this->route('publisher');

        return [
            'name' => ['sometimes', 'string', 'max:255', Rule::unique('publishers', 'name')->ignore($publisherId)],
        ];
    }
}
