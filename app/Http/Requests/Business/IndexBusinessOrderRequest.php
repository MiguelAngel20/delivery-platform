<?php

namespace App\Http\Requests\Business;

use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexBusinessOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Order::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $today = now()->toDateString();

        $this->merge([
            'from' => $this->input('from', $today),
            'to' => $this->input('to', $today),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::enum(OrderStatus::class)],
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
