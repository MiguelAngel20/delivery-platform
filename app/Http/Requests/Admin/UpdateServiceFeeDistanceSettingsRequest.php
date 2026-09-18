<?php

namespace App\Http\Requests\Admin;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;

class UpdateServiceFeeDistanceSettingsRequest extends FormRequest
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
            'base_meters' => ['required', 'integer', 'min:1', 'max:100000'],
            'base_fee' => ['required', 'numeric', 'min:0', 'max:99999.99'],
            'step_meters' => ['required', 'integer', 'min:1', 'max:100000'],
            'step_fee' => ['required', 'numeric', 'min:0', 'max:99999.99'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'base_meters' => 'metros base',
            'base_fee' => 'precio base',
            'step_meters' => 'metros por tramo',
            'step_fee' => 'incremento por tramo',
        ];
    }
}
