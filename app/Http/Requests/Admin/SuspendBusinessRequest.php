<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class SuspendBusinessRequest extends FormRequest
{
    public function authorize(): bool
    {
        $business = $this->route('business');

        return $this->user()?->can('suspend', $business) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:5', 'max:2000'],
        ];
    }
}
