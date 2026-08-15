<?php

namespace App\Http\Requests\Admin;

use App\Models\Order;
use Illuminate\Foundation\Http\FormRequest;

class ConfirmPlatformOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Order $order */
        $order = $this->route('order');

        return $this->user()?->can('confirm', $order) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'estimated_preparation_minutes' => ['required', 'integer', 'min:1', 'max:180'],
        ];
    }
}
