<?php

namespace App\Support;

use App\Models\BusinessType;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

final class BusinessTypes
{
    private const CACHE_KEY = 'business_types.active_options';

    /**
     * @return list<string>
     */
    public static function options(): array
    {
        return Cache::remember(
            self::CACHE_KEY,
            now()->addMinutes(10),
            fn (): array => BusinessType::query()
                ->active()
                ->ordered()
                ->pluck('name')
                ->values()
                ->all(),
        );
    }

    /**
     * Storefront browse categories (tipo / giro), not product categories.
     *
     * @return list<array{id: string, name: string, slug: string}>
     */
    public static function categories(): array
    {
        return BusinessType::query()
            ->active()
            ->ordered()
            ->get(['id', 'name', 'slug'])
            ->map(fn (BusinessType $type): array => [
                'id' => (string) $type->id,
                'name' => $type->name,
                'slug' => $type->slug,
            ])
            ->all();
    }

    public static function findBySlug(?string $slug): ?string
    {
        if ($slug === null || $slug === '') {
            return null;
        }

        $type = BusinessType::query()
            ->active()
            ->where('slug', $slug)
            ->first();

        return $type?->name;
    }

    public static function forgetCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    public static function uniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'tipo';
        $slug = $base;
        $suffix = 2;

        while (
            BusinessType::query()
                ->when($ignoreId !== null, fn ($query) => $query->whereKeyNot($ignoreId))
                ->where('slug', $slug)
                ->exists()
        ) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }
}
