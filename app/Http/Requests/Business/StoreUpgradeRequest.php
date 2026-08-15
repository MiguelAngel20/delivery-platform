<?php

namespace App\Http\Requests\Business;

use App\Enums\UpgradeRequestType;
use App\Models\BusinessUpgradeRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreUpgradeRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $membership = $user?->activeBusinessMembership();

        if ($user === null || $membership === null || $membership->business === null) {
            return false;
        }

        return $user->can('create', [BusinessUpgradeRequest::class, $membership->business]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $businessId = $this->user()?->activeBusinessMembership()?->business_id;

        return [
            'type' => ['required', Rule::enum(UpgradeRequestType::class)],
            'requested_quantity' => ['required', 'integer', 'min:1', 'max:100'],
            'branch_id' => [
                'nullable',
                'integer',
                Rule::exists('business_branches', 'id')
                    ->where('business_id', $businessId)
                    ->whereNull('deleted_at'),
            ],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $type = $this->input('type');

            if ($type === UpgradeRequestType::AdditionalEmployees->value && blank($this->input('branch_id'))) {
                $validator->errors()->add(
                    'branch_id',
                    'Selecciona la sucursal para la solicitud de empleados.',
                );
            }
        });
    }
}
