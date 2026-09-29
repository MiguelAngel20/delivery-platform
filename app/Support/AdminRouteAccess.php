<?php

namespace App\Support;

use App\Enums\AdminAbility;
use App\Enums\AdminSection;

final class AdminRouteAccess
{
    public const ALLOW = 'allow';

    public const OWNER = 'owner';

    /**
     * @return self::ALLOW|self::OWNER|array{section: AdminSection, ability: AdminAbility}
     */
    public static function requirement(?string $routeName): string|array
    {
        if ($routeName === null || $routeName === 'admin.home') {
            return self::ALLOW;
        }

        if (! str_starts_with($routeName, 'admin.')) {
            return self::ALLOW;
        }

        if (str_starts_with($routeName, 'admin.settings.admins')) {
            return self::OWNER;
        }

        $override = self::overrides()[$routeName] ?? null;

        if ($override !== null) {
            return $override;
        }

        $section = self::sectionFor($routeName);

        if ($section === null) {
            return self::OWNER;
        }

        return [
            'section' => $section,
            'ability' => self::abilityFor($routeName),
        ];
    }

    /**
     * @return array<string, array{section: AdminSection, ability: AdminAbility}>
     */
    private static function overrides(): array
    {
        return [
            'admin.orders.quotes.store' => [
                'section' => AdminSection::Orders,
                'ability' => AdminAbility::Update,
            ],
            'admin.orders.cancel' => [
                'section' => AdminSection::Orders,
                'ability' => AdminAbility::Update,
            ],
            'admin.cancellations.review' => [
                'section' => AdminSection::Incidents,
                'ability' => AdminAbility::Update,
            ],
            'admin.settings.index' => [
                'section' => AdminSection::Settings,
                'ability' => AdminAbility::View,
            ],
            'admin.settings.notifications.edit' => [
                'section' => AdminSection::Settings,
                'ability' => AdminAbility::View,
            ],
            'admin.settings.notifications.update' => [
                'section' => AdminSection::Settings,
                'ability' => AdminAbility::View,
            ],
            'admin.settings.activity-suspension.edit' => [
                'section' => AdminSection::Settings,
                'ability' => AdminAbility::Update,
            ],
            'admin.settings.activity-suspension.update' => [
                'section' => AdminSection::Settings,
                'ability' => AdminAbility::Update,
            ],
        ];
    }

    private static function sectionFor(string $routeName): ?AdminSection
    {
        return match (true) {
            str_starts_with($routeName, 'admin.businesses') => AdminSection::Businesses,
            str_starts_with($routeName, 'admin.business-types') => AdminSection::BusinessTypes,
            str_starts_with($routeName, 'admin.coverage') => AdminSection::Coverage,
            str_starts_with($routeName, 'admin.drivers') => AdminSection::Drivers,
            str_starts_with($routeName, 'admin.customers') => AdminSection::Customers,
            str_starts_with($routeName, 'admin.custom-orders') => AdminSection::CustomOrders,
            str_starts_with($routeName, 'admin.orders') => AdminSection::Orders,
            str_starts_with($routeName, 'admin.incidents'), str_starts_with($routeName, 'admin.cancellations') => AdminSection::Incidents,
            str_starts_with($routeName, 'admin.finance') => AdminSection::Finance,
            str_starts_with($routeName, 'admin.promotions') => AdminSection::Promotions,
            str_starts_with($routeName, 'admin.reports') => AdminSection::Reports,
            str_starts_with($routeName, 'admin.settings') => AdminSection::Settings,
            default => null,
        };
    }

    private static function abilityFor(string $routeName): AdminAbility
    {
        if (str_contains($routeName, '.destroy') || str_ends_with($routeName, '.delete')) {
            return AdminAbility::Delete;
        }

        if (preg_match('/\.(store|create)$/', $routeName) === 1) {
            return AdminAbility::Create;
        }

        if (preg_match('/\.(update|edit|approve|reject|suspend|activate|deactivate|claim|quote|pickup|resolve|confirm|ready)$/', $routeName) === 1
            || str_contains($routeName, 'block-trust')
            || str_contains($routeName, 'unblock-trust')
            || str_contains($routeName, 'mark-paid')
            || str_contains($routeName, 'resend-verification')) {
            return AdminAbility::Update;
        }

        return AdminAbility::View;
    }
}
