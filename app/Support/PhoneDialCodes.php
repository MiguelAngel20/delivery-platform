<?php

namespace App\Support;

final class PhoneDialCodes
{
    /**
     * @return list<array{iso: string, dial: string, label: string, national_length: int}>
     */
    public static function all(): array
    {
        return [
            ['iso' => 'MX', 'dial' => '+52', 'label' => 'México', 'national_length' => 10],
            ['iso' => 'GT', 'dial' => '+502', 'label' => 'Guatemala', 'national_length' => 8],
            ['iso' => 'US', 'dial' => '+1', 'label' => 'Estados Unidos', 'national_length' => 10],
            ['iso' => 'SV', 'dial' => '+503', 'label' => 'El Salvador', 'national_length' => 8],
            ['iso' => 'HN', 'dial' => '+504', 'label' => 'Honduras', 'national_length' => 8],
            ['iso' => 'NI', 'dial' => '+505', 'label' => 'Nicaragua', 'national_length' => 8],
            ['iso' => 'CR', 'dial' => '+506', 'label' => 'Costa Rica', 'national_length' => 8],
            ['iso' => 'PA', 'dial' => '+507', 'label' => 'Panamá', 'national_length' => 8],
            ['iso' => 'BZ', 'dial' => '+501', 'label' => 'Belice', 'national_length' => 7],
            ['iso' => 'CO', 'dial' => '+57', 'label' => 'Colombia', 'national_length' => 10],
            ['iso' => 'ES', 'dial' => '+34', 'label' => 'España', 'national_length' => 9],
        ];
    }

    public static function defaultDial(): string
    {
        return '+52';
    }

    /**
     * @return list<string>
     */
    public static function dials(): array
    {
        return collect(self::all())->pluck('dial')->all();
    }

    public static function nationalLength(string $dial): ?int
    {
        $row = collect(self::all())->firstWhere('dial', $dial);

        return is_array($row) ? $row['national_length'] : null;
    }

    public static function e164(string $dial, string $national): string
    {
        $digits = preg_replace('/\D+/', '', $national) ?? '';

        return $dial.$digits;
    }

    /**
     * @return list<array{dial: string, label: string, national_length: int}>
     */
    public static function options(): array
    {
        return collect(self::all())
            ->map(fn (array $row): array => [
                'dial' => $row['dial'],
                'label' => $row['dial'],
                'national_length' => $row['national_length'],
            ])
            ->values()
            ->all();
    }
}
