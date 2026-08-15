<?php

namespace App\Http\Requests\Driver;

use App\Enums\CancellationReasonCode;
use App\Models\Order;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CannotContinueRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Order $order */
        $order = $this->route('order');

        return $this->user()?->can('manageDelivery', $order) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reason_code' => ['required', Rule::enum(CancellationReasonCode::class)->only(
                CancellationReasonCode::forDriver(),
            )],
            'description' => ['required', 'string', 'max:2000'],
        ];
    }
}
