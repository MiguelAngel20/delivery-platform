<?php

namespace App\Support;

use App\Enums\AdminAbility;
use App\Enums\AdminSection;
use App\Enums\UserRole;
use App\Models\AdminPermission;
use App\Models\User;

final class AdminAccess
{
    public static function allows(?User $user, AdminSection $section, AdminAbility $ability): bool
    {
        if ($user === null || ! $user->hasRole(UserRole::SystemAdmin) || ! $user->isActive()) {
            return false;
        }

        if ($user->isPlatformOwner()) {
            return true;
        }

        $user->loadMissing('adminPermissions');

        /** @var AdminPermission|null $permission */
        $permission = $user->adminPermissions->first(
            fn (AdminPermission $row): bool => $row->section === $section,
        );

        if ($permission === null) {
            return false;
        }

        return match ($ability) {
            AdminAbility::View => $permission->can_view
                || $permission->can_create
                || $permission->can_update
                || $permission->can_delete,
            AdminAbility::Create => $permission->can_create,
            AdminAbility::Update => $permission->can_update,
            AdminAbility::Delete => $permission->can_delete,
        };
    }

    /**
     * @param  array<string, array{view?: bool, create?: bool, update?: bool, delete?: bool}>  $input
     */
    public static function sync(User $user, array $input): void
    {
        foreach (AdminSection::assignable() as $section) {
            $flags = $input[$section->value] ?? [];
            $create = (bool) ($flags['create'] ?? false);
            $update = (bool) ($flags['update'] ?? false);
            $delete = (bool) ($flags['delete'] ?? false);
            $view = (bool) ($flags['view'] ?? false) || $create || $update || $delete;

            AdminPermission::query()->updateOrCreate(
                [
                    'user_id' => $user->id,
                    'section' => $section->value,
                ],
                [
                    'can_view' => $view,
                    'can_create' => $create,
                    'can_update' => $update,
                    'can_delete' => $delete,
                ],
            );
        }
    }

    /**
     * @param  array<string, array{view?: bool, create?: bool, update?: bool, delete?: bool}>  $overrides
     * @return array<string, array{view: bool, create: bool, update: bool, delete: bool}>
     */
    public static function permissionInput(array $overrides = []): array
    {
        $payload = [];

        foreach (AdminSection::assignable() as $section) {
            $flags = $overrides[$section->value] ?? [];
            $payload[$section->value] = [
                'view' => (bool) ($flags['view'] ?? false),
                'create' => (bool) ($flags['create'] ?? false),
                'update' => (bool) ($flags['update'] ?? false),
                'delete' => (bool) ($flags['delete'] ?? false),
            ];
        }

        return $payload;
    }

    /**
     * @return array{is_owner: bool, sections: array<string, array{view: bool, create: bool, update: bool, delete: bool}>}
     */
    public static function forFrontend(User $user): array
    {
        $sections = [];

        foreach (AdminSection::assignable() as $section) {
            $sections[$section->value] = [
                'view' => self::allows($user, $section, AdminAbility::View),
                'create' => self::allows($user, $section, AdminAbility::Create),
                'update' => self::allows($user, $section, AdminAbility::Update),
                'delete' => self::allows($user, $section, AdminAbility::Delete),
            ];
        }

        return [
            'is_owner' => $user->isPlatformOwner(),
            'sections' => $sections,
        ];
    }

    /**
     * @return list<array{value: string, label: string, hint: string|null}>
     */
    public static function sectionOptions(): array
    {
        return array_map(
            fn (AdminSection $section): array => [
                'value' => $section->value,
                'label' => $section->label(),
                'hint' => $section->hint(),
            ],
            AdminSection::assignable(),
        );
    }
}
