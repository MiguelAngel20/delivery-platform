<?php

namespace App\Http\Requests\Admin;

use App\Models\Business;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBusinessDriverRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Business $business */
        $business = $this->route('business');

        return $this->user()?->can('update', $business) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Business $business */
        $business = $this->route('business');

        return [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['required', 'string', 'max:30'],
            'branch_ids' => ['required', 'array', 'min:1'],
            'branch_ids.*' => [
                'integer',
                Rule::exists('business_branches', 'id')
                    ->where('business_id', $business->id)
                    ->whereNull('deleted_at'),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'branch_ids.required' => 'Asigna al menos una sucursal.',
            'branch_ids.min' => 'Asigna al menos una sucursal.',
            'branch_ids.*.exists' => 'Una o más sucursales no pertenecen a esta empresa.',
        ];
    }
}
