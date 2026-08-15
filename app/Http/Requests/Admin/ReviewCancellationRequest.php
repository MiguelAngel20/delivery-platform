<?php

namespace App\Http\Requests\Admin;

use App\Enums\CancellationResponsibility;
use App\Models\Incident;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReviewCancellationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('reviewCancellation', Incident::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'responsibility' => ['required', Rule::enum(CancellationResponsibility::class)->except([
                CancellationResponsibility::UnderReview,
            ])],
            'review_notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
