<?php

namespace App\Http\Requests\Business;

use App\Enums\BusinessUserRole;
use App\Enums\BusinessUserStatus;
use App\Models\BusinessUser;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexBusinessEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', BusinessUser::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'role' => ['nullable', Rule::enum(BusinessUserRole::class)],
            'status' => ['nullable', Rule::enum(BusinessUserStatus::class)],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
