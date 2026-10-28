<?php

namespace App\Enums;

enum UserRole: string
{
    case Customer = 'customer';
    case Admin = 'admin';
    case SuperAdmin = 'super_admin';

    public function isStaff(): bool
    {
        return $this === self::Admin || $this === self::SuperAdmin;
    }

    public function isSuperAdmin(): bool
    {
        return $this === self::SuperAdmin;
    }
}
