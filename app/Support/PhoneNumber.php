<?php

namespace App\Support;

final class PhoneNumber
{
    public static function canonicalize(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if ($digits === '') {
            return '';
        }

        return '+'.$digits;
    }

    public static function same(string $left, string $right): bool
    {
        $canonicalLeft = self::canonicalize($left);
        $canonicalRight = self::canonicalize($right);

        return $canonicalLeft !== '' && $canonicalLeft === $canonicalRight;
    }

    public static function mask(string $phone): string
    {
        $canonical = self::canonicalize($phone);

        if (strlen($canonical) < 8) {
            return '***';
        }

        return substr($canonical, 0, 3).str_repeat('*', strlen($canonical) - 7).substr($canonical, -4);
    }
}
