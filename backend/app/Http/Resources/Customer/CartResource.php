<?php

namespace App\Http\Resources\Customer;

use App\Models\Cart;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Cart */
class CartResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $items = $this->whenLoaded('items');
        $subtotal = 0;

        if ($this->relationLoaded('items')) {
            foreach ($this->items as $item) {
                if ($item->relationLoaded('book') && $item->book) {
                    $subtotal += $item->book->price * $item->quantity;
                }
            }
        }

        return [
            'id' => $this->id,
            'items' => CartItemResource::collection($items),
            'item_count' => $this->when($this->relationLoaded('items'), fn () => (int) $this->items->sum('quantity')),
            'subtotal_amount' => $subtotal,
        ];
    }
}
