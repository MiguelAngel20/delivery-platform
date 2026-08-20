<?php

namespace App\Support\Businesses;

trait EmployeeFormValidation
{
    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'first_name.required' => 'El nombre es obligatorio.',
            'first_name.max' => 'El nombre no puede superar :max caracteres.',
            'last_name.required' => 'Los apellidos son obligatorios.',
            'last_name.max' => 'Los apellidos no pueden superar :max caracteres.',
            'email.required' => 'El correo es obligatorio.',
            'email.email' => 'Ingresa un correo válido.',
            'email.max' => 'El correo no puede superar :max caracteres.',
            'phone.required' => 'El teléfono es obligatorio.',
            'phone.max' => 'El teléfono no puede superar :max caracteres.',
            'role.required' => 'Selecciona un rol.',
            'role.enum' => 'El rol seleccionado no es válido.',
            'status.required' => 'Selecciona un estado.',
            'status.enum' => 'El estado seleccionado no es válido.',
            'branch_ids.required' => 'Selecciona una sucursal asignada.',
            'branch_ids.array' => 'Selecciona una sucursal asignada.',
            'branch_ids.size' => 'Debes asignar exactamente una sucursal.',
            'branch_ids.*.integer' => 'La sucursal seleccionada no es válida.',
            'branch_ids.*.exists' => 'Una o más sucursales no pertenecen a tu empresa.',
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
            'email' => 'correo',
            'phone' => 'teléfono',
            'role' => 'rol',
            'status' => 'estado',
            'branch_ids' => 'sucursal asignada',
        ];
    }
}
