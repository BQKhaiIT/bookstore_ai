<?php

namespace App\Http\Resources\Customer;

use App\Http\Resources\Admin\AuthorResource;
use App\Http\Resources\Admin\CategoryResource;
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
            'isbn' => $this->isbn,
            'title' => $this->title,
            'slug' => $this->slug,
            'description' => $this->description,
            'price' => $this->price,
            'stock_quantity' => $this->stock_quantity,
            'cover_url' => $this->cover_url,
            'status' => $this->status instanceof \BackedEnum ? $this->status->value : $this->status,
            'publisher' => $this->whenLoaded('publisher', fn () => [
                'id' => $this->publisher?->id,
                'name' => $this->publisher?->name,
            ]),
            'authors' => AuthorResource::collection($this->whenLoaded('authors')),
            'categories' => CategoryResource::collection($this->whenLoaded('categories')),
            'average_rating' => $this->when(isset($this->average_rating), fn () => $this->average_rating),
            'reviews_count' => $this->when(isset($this->reviews_count), fn () => $this->reviews_count),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
