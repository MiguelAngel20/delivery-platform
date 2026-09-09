<?php

namespace App\Http\Requests\Admin;

use App\Enums\BusinessTypeStatus;
use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBusinessTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole(UserRole::SystemAdmin) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100', Rule::unique('business_types', 'name')],
            'description' => ['nullable', 'string', 'max:1000'],
            'status' => ['required', Rule::enum(BusinessTypeStatus::class)],
        ];
    }
}
