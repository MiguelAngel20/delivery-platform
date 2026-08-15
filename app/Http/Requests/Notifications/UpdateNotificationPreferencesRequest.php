<?php

namespace App\Http\Requests\Notifications;

use App\Services\Notifications\NotificationPreferenceService;
use Illuminate\Foundation\Http\FormRequest;

class UpdateNotificationPreferencesRequest extends FormRequest
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
        $keys = app(NotificationPreferenceService::class)
            ->editableKeysForRole($this->user()->role);

        $rules = [];

        foreach ($keys as $key) {
            $rules[$key] = ['sometimes', 'boolean'];
        }

        return $rules;
    }
}
