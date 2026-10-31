<?php

namespace App\Enums;

enum BannerType: string
{
    case Hero = 'hero';
    case Banner = 'banner';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
