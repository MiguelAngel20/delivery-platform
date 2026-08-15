<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBusinessLimitsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $business = $this->route('business');

        return $this->user()?->can('update', $business) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'max_branches' => ['required', 'integer', 'min:1', 'max:1000'],
            'max_business_admins' => ['required', 'integer', 'min:1', 'max:1000'],
            'max_employees_per_branch' => ['required', 'integer', 'min:1', 'max:1000'],
        ];
    }
}
