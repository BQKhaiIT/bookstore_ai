<?php

namespace App\Enums;

enum OrderStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Packing = 'packing';
    case Shipping = 'shipping';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';
}
