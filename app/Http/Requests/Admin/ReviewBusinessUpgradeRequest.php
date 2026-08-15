<?php

namespace App\Http\Requests\Admin;

use App\Models\BusinessUpgradeRequest;
use Illuminate\Foundation\Http\FormRequest;

class ReviewBusinessUpgradeRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var BusinessUpgradeRequest $upgradeRequest */
        $upgradeRequest = $this->route('upgradeRequest');

        return $this->user()?->can('review', $upgradeRequest) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'notes' => ['nullable', 'string', 'max:2000'],
            'apply_limit_increase' => ['sometimes', 'boolean'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
