<?php

namespace App\Http\Requests\Admin;

use App\Models\User;
use App\Support\AdminPermissionRules;
use App\Support\ApplicationPassword;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAdminUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isPlatformOwner() === true;
    }

    protected function prepareForValidation(): void
    {
        $password = $this->input('password');

        $this->merge([
            'email' => strtolower(trim((string) $this->input('email'))),
            'phone' => trim((string) $this->input('phone')),
            'first_name' => trim((string) $this->input('first_name')),
            'last_name' => trim((string) $this->input('last_name')),
            'password' => $password === '' ? null : $password,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $adminUser = $this->route('adminUser');
        $userId = $adminUser instanceof User ? $adminUser->id : null;

        return [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($userId)],
            'phone' => ['required', 'string', 'max:30', Rule::unique('users', 'phone')->ignore($userId)],
            'password' => ['nullable', 'string', ...array_values(array_filter(
                ApplicationPassword::validationRules(),
                fn (mixed $rule): bool => $rule !== 'required',
            ))],
            ...AdminPermissionRules::rules(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'first_name' => 'nombre',
            'last_name' => 'apellido',
            'email' => 'correo',
            'phone' => 'teléfono',
            'password' => 'contraseña',
        ];
    }
}
