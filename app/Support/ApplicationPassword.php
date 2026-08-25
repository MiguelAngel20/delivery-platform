<?php

namespace App\Support;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rules\Password;

final class ApplicationPassword
{
    public static function rule(): Password
    {
        $rule = Password::min(8)
            ->numbers()
            ->symbols();

        if (app()->isProduction()) {
            $rule->uncompromised();
        }

        return $rule;
    }

    /**
     * @return list<ValidationRule|string|Password>
     */
    public static function validationRules(bool $confirmed = true): array
    {
        $rules = [
            'required',
            'string',
            self::rule(),
            'regex:/[A-Z]/',
        ];

        if ($confirmed) {
            $rules[] = 'confirmed';
        }

        return $rules;
    }
}
