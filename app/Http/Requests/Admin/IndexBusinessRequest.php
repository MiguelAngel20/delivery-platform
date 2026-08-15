<?php

namespace App\Http\Requests\Admin;

use App\Enums\BusinessDeliveryMode;
use App\Enums\BusinessOperationMode;
use App\Enums\BusinessStatus;
use App\Models\Business;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexBusinessRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Business::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::enum(BusinessStatus::class)],
            'operation_mode' => ['nullable', Rule::enum(BusinessOperationMode::class)],
            'delivery_mode' => ['nullable', Rule::enum(BusinessDeliveryMode::class)],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
