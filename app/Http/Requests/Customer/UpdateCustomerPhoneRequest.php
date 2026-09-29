<?php

namespace App\Http\Requests\Customer;

use App\Enums\UserRole;
use App\Support\PhoneDialCodes;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateCustomerPhoneRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === UserRole::Customer;
    }

    protected function prepareForValidation(): void
    {
        $dial = (string) $this->input('phone_dial_code', PhoneDialCodes::defaultDial());
        $national = preg_replace('/\D+/', '', (string) $this->input('phone_national')) ?? '';

        $this->merge([
            'phone_dial_code' => $dial,
            'phone_national' => $national,
            'phone' => PhoneDialCodes::e164($dial, $national),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'phone_dial_code' => ['required', 'string', Rule::in([PhoneDialCodes::customerSmsDial()])],
            'phone_national' => ['required', 'string'],
            'phone' => [
                'required',
                'string',
                'max:20',
                Rule::unique('users', 'phone')->ignore($this->user()?->id),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'phone_dial_code.required' => 'Selecciona el código de país.',
            'phone_dial_code.in' => 'Por ahora solo puedes usar la lada +52.',
            'phone_national.required' => 'Indica tu número de teléfono.',
            'phone.unique' => 'Ya existe una cuenta con este teléfono.',
        ];
    }

    /**
     * @return list<\Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $dial = (string) $this->input('phone_dial_code');
                $national = (string) $this->input('phone_national');
                $expected = PhoneDialCodes::nationalLength($dial);

                if ($expected !== null && strlen($national) !== $expected) {
                    $validator->errors()->add(
                        'phone_national',
                        "El número debe tener {$expected} dígitos para ese país.",
                    );
                }
            },
        ];
    }
}
