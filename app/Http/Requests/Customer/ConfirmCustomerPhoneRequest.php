<?php

namespace App\Http\Requests\Customer;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;

class ConfirmCustomerPhoneRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === UserRole::Customer;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'firebase_id_token' => ['required', 'string', 'max:8192'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'firebase_id_token.required' => 'No se pudo confirmar la verificación del teléfono.',
        ];
    }
}
