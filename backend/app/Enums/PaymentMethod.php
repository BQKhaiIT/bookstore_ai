<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Cod = 'cod';
    case SandboxOnline = 'sandbox_online';
}
