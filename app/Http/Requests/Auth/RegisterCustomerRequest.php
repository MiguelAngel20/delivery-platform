<?php

namespace App\Http\Requests\Auth;

use App\Support\PhoneDialCodes;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;

class RegisterCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() === null;
    }

    protected function prepareForValidation(): void
    {
        $dial = (string) $this->input('phone_dial_code', PhoneDialCodes::defaultDial());
        $national = preg_replace('/\D+/', '', (string) $this->input('phone_national')) ?? '';

        $this->merge([
            'email' => strtolower(trim((string) $this->input('email'))),
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
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'phone_dial_code' => ['required', 'string', Rule::in(PhoneDialCodes::dials())],
            'phone_national' => ['required', 'string'],
            'phone' => ['required', 'string', 'max:20', 'unique:users,phone'],
            'password' => array_values(array_filter([
                'required',
                'string',
                'confirmed',
                Password::defaults(),
            ])),
            'address_label' => ['nullable', 'string', 'max:100'],
            'address_text' => ['required', 'string', 'max:255'],
            'formatted_address' => ['nullable', 'string', 'max:500'],
            'reference' => ['nullable', 'string'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'place_id' => ['nullable', 'string', 'max:255'],
            'google_maps_url' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'first_name' => 'nombre',
            'last_name' => 'apellidos',
            'email' => 'correo electrónico',
            'phone_dial_code' => 'código de país',
            'phone_national' => 'teléfono',
            'phone' => 'teléfono',
            'password' => 'contraseña',
            'password_confirmation' => 'confirmación de contraseña',
            'address_label' => 'etiqueta de dirección',
            'address_text' => 'dirección de entrega',
            'reference' => 'referencia',
            'latitude' => 'ubicación',
            'longitude' => 'ubicación',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'first_name.required' => 'Indica tu nombre.',
            'first_name.max' => 'El nombre no puede superar :max caracteres.',
            'last_name.required' => 'Indica tus apellidos.',
            'last_name.max' => 'Los apellidos no pueden superar :max caracteres.',
            'email.required' => 'Indica tu correo electrónico.',
            'email.email' => 'El correo electrónico no es válido.',
            'email.max' => 'El correo electrónico no puede superar :max caracteres.',
            'email.unique' => 'Ya existe una cuenta con este correo. Inicia sesión.',
            'phone_dial_code.required' => 'Selecciona el código de país.',
            'phone_dial_code.in' => 'El código de país no es válido.',
            'phone_national.required' => 'Indica tu número de teléfono.',
            'phone.required' => 'Indica tu número de teléfono.',
            'phone.max' => 'El teléfono no puede superar :max caracteres.',
            'phone.unique' => 'Ya existe una cuenta con este teléfono. Inicia sesión.',
            'password.required' => 'Elige una contraseña para entrar después.',
            'password.confirmed' => 'La confirmación de contraseña no coincide.',
            'password.min' => 'La contraseña debe tener al menos :min caracteres.',
            'password.letters' => 'La contraseña debe incluir al menos una letra.',
            'password.mixed' => 'La contraseña debe incluir mayúsculas y minúsculas.',
            'password.numbers' => 'La contraseña debe incluir al menos un número.',
            'password.symbols' => 'La contraseña debe incluir al menos un símbolo.',
            'password.uncompromised' => 'Esa contraseña apareció en una filtración de datos. Elige otra.',
            'address_label.max' => 'La etiqueta no puede superar :max caracteres.',
            'address_text.required' => 'Selecciona tu dirección de entrega en el mapa.',
            'address_text.max' => 'La dirección no puede superar :max caracteres.',
            'latitude.required' => 'Selecciona tu dirección de entrega en el mapa.',
            'latitude.numeric' => 'La ubicación del mapa no es válida.',
            'latitude.between' => 'La ubicación del mapa no es válida.',
            'longitude.required' => 'Selecciona tu dirección de entrega en el mapa.',
            'longitude.numeric' => 'La ubicación del mapa no es válida.',
            'longitude.between' => 'La ubicación del mapa no es válida.',
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
