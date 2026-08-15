<?php

namespace App\Http\Requests\Business;

use App\Enums\IncidentType;
use App\Models\Order;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReportIncidentRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Order $order */
        $order = $this->route('order');

        return $this->user()?->can('reportIncident', $order) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(IncidentType::class)->only(
                IncidentType::forBusiness(),
            )],
            'description' => ['required', 'string', 'max:2000'],
        ];
    }
}
