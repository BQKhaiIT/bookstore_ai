<?php

namespace App\Http\Resources\Admin;

use App\Models\Book;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Book */
class BookResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'publisher_id' => $this->publisher_id,
            'publisher' => $this->whenLoaded('publisher', fn () => [
                'id' => $this->publisher?->id,
                'name' => $this->publisher?->name,
            ]),
            'isbn' => $this->isbn,
            'title' => $this->title,
            'slug' => $this->slug,
            'description' => $this->description,
            'price' => $this->price,
            'stock_quantity' => $this->stock_quantity,
            'cover_url' => $this->cover_url,
            'status' => $this->status instanceof \BackedEnum ? $this->status->value : $this->status,
            'author_ids' => $this->whenLoaded('authors', fn () => $this->authors->pluck('id')->values()),
            'category_ids' => $this->whenLoaded('categories', fn () => $this->categories->pluck('id')->values()),
            'authors' => AuthorResource::collection($this->whenLoaded('authors')),
            'categories' => CategoryResource::collection($this->whenLoaded('categories')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
