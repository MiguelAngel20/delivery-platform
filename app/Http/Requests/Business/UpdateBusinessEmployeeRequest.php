<?php

namespace App\Http\Requests\Business;

use App\Enums\BusinessUserRole;
use App\Enums\BusinessUserStatus;
use App\Models\Business;
use App\Models\BusinessUser;
use App\Support\Businesses\EmployeeFormValidation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBusinessEmployeeRequest extends FormRequest
{
    use EmployeeFormValidation;

    public function authorize(): bool
    {
        /** @var BusinessUser $businessUser */
        $businessUser = $this->route('businessUser');

        return $this->user()?->can('update', $businessUser) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var BusinessUser $businessUser */
        $businessUser = $this->route('businessUser');
        /** @var Business $business */
        $business = $businessUser->business;

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
                    ->where('business_id', $business->id)
                    ->whereNull('deleted_at'),
            ],
        ];
    }
}
