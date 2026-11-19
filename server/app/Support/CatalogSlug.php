<?php

namespace App\Support;

use App\Models\Hotel;
use App\Models\Trip;
use Illuminate\Support\Str;

class CatalogSlug
{
    public static function generate(Trip|Hotel $record, string $name): string
    {
        $base = Str::slug($name) ?: 'ponuda';
        $slug = rtrim(Str::substr($base, 0, 255), '-');
        $number = 2;

        while ($record->newQuery()->withTrashed()->where('slug', $slug)->exists()) {
            $suffix = '-'.$number++;
            $slug = rtrim(Str::substr($base, 0, 255 - strlen($suffix)), '-').$suffix;
        }

        return $slug;
    }
}
