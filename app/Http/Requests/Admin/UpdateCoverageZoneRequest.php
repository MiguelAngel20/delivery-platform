<?php

namespace App\Http\Requests\Admin;

use App\Enums\CoverageScopeType;
use App\Enums\CoverageZoneType;
use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateCoverageZoneRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:150'],
            'scope_type' => ['required', Rule::enum(CoverageScopeType::class)],
            'scope_id' => ['nullable', 'integer'],
            'zone_type' => ['required', Rule::enum(CoverageZoneType::class)],
            'center_latitude' => ['required', 'numeric', 'between:-90,90'],
            'center_longitude' => ['required', 'numeric', 'between:-180,180'],
            'radius_meters' => ['required', 'integer', 'min:100', 'max:100000'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->input('scope_type') === CoverageScopeType::BusinessBranch->value
                && blank($this->input('scope_id'))) {
                $validator->errors()->add('scope_id', 'Selecciona una sucursal.');
            }

            if ($this->input('scope_type') === CoverageScopeType::Platform->value) {
                $this->merge(['scope_id' => null]);
            }

            if ($this->input('zone_type') !== CoverageZoneType::Radius->value) {
                $validator->errors()->add('zone_type', 'En V1 solo se admiten zonas de tipo radio.');
            }
        });
    }
}
