<?php

namespace App\Http\Resources\Customer;

use App\Models\CartItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin CartItem */
class CartItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $book = $this->whenLoaded('book');
        $unitPrice = $this->relationLoaded('book') && $this->book ? $this->book->price : 0;

        return [
            'id' => $this->id,
            'book_id' => $this->book_id,
            'quantity' => $this->quantity,
            'unit_price' => $unitPrice,
            'subtotal' => $unitPrice * $this->quantity,
            'book' => new BookResource($book),
        ];
    }
}
