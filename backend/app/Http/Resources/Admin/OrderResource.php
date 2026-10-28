<?php

namespace App\Http\Resources\Admin;

use App\Http\Resources\UserResource;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Order */
class OrderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_code' => $this->order_code,
            'status' => $this->status instanceof \BackedEnum ? $this->status->value : $this->status,
            'subtotal_amount' => $this->subtotal_amount,
            'shipping_fee' => $this->shipping_fee,
            'total_amount' => $this->total_amount,
            'shipping_address_snapshot' => $this->shipping_address_snapshot,
            'customer_note' => $this->customer_note,
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'user' => new UserResource($this->whenLoaded('user')),
            'items' => OrderItemResource::collection($this->whenLoaded('items')),
            'payment' => new PaymentResource($this->whenLoaded('payment')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
