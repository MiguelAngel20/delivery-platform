<?php

namespace App\Http\Requests\Admin;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;

class UpdateDriverCommissionRequest extends FormRequest
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
            'pays_commission' => ['required', 'boolean'],
            'commission_per_order' => [
                'required',
                'numeric',
                'min:0',
                'max:10',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'pays_commission' => 'paga comisión',
            'commission_per_order' => 'comisión por pedido',
        ];
    }
}
