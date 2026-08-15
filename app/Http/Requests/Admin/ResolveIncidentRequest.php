<?php

namespace App\Http\Requests\Admin;

use App\Models\Incident;
use Illuminate\Foundation\Http\FormRequest;

class ResolveIncidentRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Incident $incident */
        $incident = $this->route('incident');

        return $this->user()?->can('resolve', $incident) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'resolution' => ['required', 'string', 'max:2000'],
        ];
    }
}
