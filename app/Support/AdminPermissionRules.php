<?php

namespace App\Support;

use App\Enums\AdminSection;

final class AdminPermissionRules
{
    /**
     * @return array<string, list<string>>
     */
    public static function rules(): array
    {
        $rules = [
            'permissions' => ['required', 'array'],
        ];

        foreach (AdminSection::assignable() as $section) {
            foreach (['view', 'create', 'update', 'delete'] as $flag) {
                $rules['permissions.'.$section->value.'.'.$flag] = ['required', 'boolean'];
            }
        }

        return $rules;
    }
}
