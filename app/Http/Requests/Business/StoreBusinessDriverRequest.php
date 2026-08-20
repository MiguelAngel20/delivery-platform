<?php

namespace App\Http\Requests\Business;

use App\Models\Business;
use App\Support\BusinessAccess;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBusinessDriverRequest extends FormRequest
{
    public function authorize(): bool
    {
        $business = $this->currentBusiness();

        if ($business === null) {
            return false;
        }

        return $this->user()?->can('update', $business)
            && $business->delivery_mode->usesOwnDrivers();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $business = $this->currentBusiness();
        $accessibleBranchIds = app(BusinessAccess::class)->accessibleBranchIds(
            $this->user(),
            $business,
        );

        return [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['required', 'string', 'max:30'],
            'branch_ids' => ['required', 'array', 'min:1'],
            'branch_ids.*' => [
                'integer',
                Rule::in($accessibleBranchIds),
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
            'branch_ids.*.in' => 'Una o más sucursales no están disponibles para tu cuenta.',
        ];
    }

    private function currentBusiness(): ?Business
    {
        return $this->user()?->activeBusinessMembership()?->business;
    }
}
