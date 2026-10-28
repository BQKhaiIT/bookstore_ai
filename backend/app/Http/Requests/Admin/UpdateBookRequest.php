<?php

namespace App\Http\Requests\Admin;

use App\Enums\BookStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBookRequest extends FormRequest
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
        $bookId = $this->route('book')?->id ?? $this->route('book');

        return [
            'publisher_id' => ['sometimes', 'nullable', 'integer', 'exists:publishers,id'],
            'isbn' => ['sometimes', 'nullable', 'string', 'max:20', Rule::unique('books', 'isbn')->ignore($bookId)],
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'price' => ['sometimes', 'integer', 'min:0'],
            'stock_quantity' => ['sometimes', 'integer', 'min:0'],
            'status' => ['sometimes', Rule::enum(BookStatus::class)],
            'author_ids' => ['sometimes', 'array'],
            'author_ids.*' => ['integer', 'exists:authors,id'],
            'category_ids' => ['sometimes', 'array'],
            'category_ids.*' => ['integer', 'exists:categories,id'],
        ];
    }
}
