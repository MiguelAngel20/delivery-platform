<?php

namespace App\Support;

use Illuminate\Support\Str;

final class BusinessTypes
{
    /**
     * @return list<string>
     */
    public static function options(): array
    {
        return [
            'Restaurante',
            'Comida rápida',
            'Postres',
            'Cafetería',
            'Farmacia',
            'Tienda',
            'Otro',
        ];
    }

    /**
     * Storefront browse categories (tipo / giro), not product categories.
     *
     * @return list<array{id: string, name: string, slug: string}>
     */
    public static function categories(): array
    {
        return collect(self::options())
            ->values()
            ->map(fn (string $name, int $index): array => [
                'id' => 'type-'.($index + 1),
                'name' => $name,
                'slug' => Str::slug($name),
            ])
            ->all();
    }

    public static function findBySlug(?string $slug): ?string
    {
        if ($slug === null || $slug === '') {
            return null;
        }

        return collect(self::options())
            ->first(fn (string $type): bool => Str::slug($type) === $slug);
    }
}
