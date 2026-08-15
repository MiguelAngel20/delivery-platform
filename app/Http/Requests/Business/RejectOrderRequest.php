<?php

namespace App\Http\Requests\Business;

use Illuminate\Foundation\Http\FormRequest;

class RejectOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        $order = $this->route('order');

        return $this->user()?->can('reject', $order) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:500'],
        ];
    }
}
