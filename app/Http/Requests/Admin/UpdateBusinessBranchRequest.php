<?php

namespace App\Http\Requests\Admin;

use App\Enums\BranchStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBusinessBranchRequest extends FormRequest
{
    public function authorize(): bool
    {
        $branch = $this->route('branch');

        return $this->user()?->can('update', $branch) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'address_text' => ['required', 'string', 'max:500'],
            'formatted_address' => ['nullable', 'string', 'max:500'],
            'reference' => ['nullable', 'string', 'max:500'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'place_id' => ['nullable', 'string', 'max:255'],
            'google_maps_url' => ['nullable', 'url', 'max:1000'],
            'status' => ['required', Rule::enum(BranchStatus::class)],
        ];
    }
}
