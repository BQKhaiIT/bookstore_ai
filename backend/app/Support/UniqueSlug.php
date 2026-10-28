<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class UniqueSlug
{
    public static function forTitle(string $title, Model $model, string $column = 'slug'): string
    {
        $base = Str::slug($title);
        if ($base === '') {
            $base = 'item';
        }

        $slug = $base;
        $suffix = 1;

        while ($model->newQuery()->where($column, $slug)->exists()) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }
}
