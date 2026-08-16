<?php

namespace App\Http\Requests\Admin;

use App\Enums\BusinessDeliveryMode;
use App\Enums\BusinessOperationMode;
use App\Enums\BusinessStatus;
use App\Models\Business;
use App\Support\BusinessHours;
use App\Support\BusinessTypes;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBusinessRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Business::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->mergeOpeningHours();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'business_type' => ['required', 'string', Rule::in(BusinessTypes::options())],
            'operation_mode' => ['required', Rule::enum(BusinessOperationMode::class)],
            'delivery_mode' => ['required', Rule::enum(BusinessDeliveryMode::class)],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'status' => ['required', Rule::enum(BusinessStatus::class)],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'banner' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            ...BusinessHours::validationRules(),
        ];
    }

    /**
     * @return list<\Closure>
     */
    public function after(): array
    {
        return BusinessHours::afterValidation();
    }

    private function mergeOpeningHours(): void
    {
        $hours = $this->input('opening_hours');

        if (is_string($hours)) {
            $decoded = json_decode($hours, true);
            $hours = is_array($decoded) ? $decoded : [];
        }

        if (! is_array($hours)) {
            $hours = [];
        }

        $this->merge([
            'opening_hours' => collect($hours)
                ->map(function (mixed $row): mixed {
                    if (! is_array($row)) {
                        return $row;
                    }

                    return [
                        ...$row,
                        'is_open' => filter_var($row['is_open'] ?? false, FILTER_VALIDATE_BOOLEAN),
                    ];
                })
                ->all(),
        ]);
    }
}
