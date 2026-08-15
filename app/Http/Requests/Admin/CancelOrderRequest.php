<?php

namespace App\Http\Requests\Admin;

use App\Enums\CancellationReasonCode;
use App\Enums\CancellationResponsibility;
use App\Models\Order;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CancelOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Order $order */
        $order = $this->route('order');

        return $this->user()?->can('cancel', $order) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reason_code' => ['required', Rule::enum(CancellationReasonCode::class)],
            'reason' => ['nullable', 'string', 'max:2000'],
            'responsibility' => ['nullable', Rule::enum(CancellationResponsibility::class)],
        ];
    }
}
