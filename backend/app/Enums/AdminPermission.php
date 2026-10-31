<?php

namespace App\Enums;

enum AdminPermission: string
{
    case Dashboard = 'dashboard';
    case Books = 'books';
    case Categories = 'categories';
    case Authors = 'authors';
    case Publishers = 'publishers';
    case Orders = 'orders';
    case Payments = 'payments';
    case Reviews = 'reviews';
    case Users = 'users';
    case Banners = 'banners';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return self::values();
    }
}
