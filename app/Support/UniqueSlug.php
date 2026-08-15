<?php

namespace App\Support;

use App\Models\Business;
use Illuminate\Support\Str;

final class UniqueSlug
{
    public static function forBusiness(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name);
        $base = $base !== '' ? $base : 'empresa';
        $slug = $base;
        $suffix = 2;

        while (
            Business::withTrashed()
                ->where('slug', $slug)
                ->when($ignoreId !== null, fn ($query) => $query->where('id', '!=', $ignoreId))
                ->exists()
        ) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }
}
