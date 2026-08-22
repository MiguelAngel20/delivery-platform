<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class VerifyCustomerEmailRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() === null;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => preg_replace('/\D+/', '', (string) $this->input('code')) ?? '',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'size:6'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.required' => 'Ingresa el código de 6 dígitos.',
            'code.size' => 'El código debe tener 6 dígitos.',
        ];
    }
}
