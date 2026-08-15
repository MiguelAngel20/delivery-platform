<?php

namespace App\Http\Requests\Notifications;

use App\Enums\PushDeviceType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegisterPushDeviceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'token' => ['required', 'string', 'max:512'],
            'device_type' => ['nullable', 'string', Rule::enum(PushDeviceType::class)],
            'browser' => ['nullable', 'string', 'max:80'],
            'platform' => ['nullable', 'string', 'max:80'],
            'device_name' => ['nullable', 'string', 'max:120'],
        ];
    }
}
