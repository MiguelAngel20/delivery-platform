<?php

namespace App\Http\Requests\Business;

use App\Enums\BusinessUserRole;
use App\Enums\BusinessUserStatus;
use App\Models\BusinessUser;
use App\Support\Businesses\EmployeeFormValidation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBusinessEmployeeRequest extends FormRequest
{
    use EmployeeFormValidation;

    public function authorize(): bool
    {
        $user = $this->user();
        $membership = $user?->activeBusinessMembership();

        if ($user === null || $membership === null || $membership->business === null) {
            return false;
        }

        return $user->can('create', [BusinessUser::class, $membership->business]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $businessId = $this->user()?->activeBusinessMembership()?->business_id;

        return [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['required', 'string', 'max:30'],
            'role' => ['required', Rule::enum(BusinessUserRole::class)],
            'status' => ['required', Rule::enum(BusinessUserStatus::class)],
            'branch_ids' => ['required', 'array', 'size:1'],
            'branch_ids.*' => [
                'integer',
                Rule::exists('business_branches', 'id')
                    ->where('business_id', $businessId)
                    ->whereNull('deleted_at'),
            ],
        ];
    }
}
