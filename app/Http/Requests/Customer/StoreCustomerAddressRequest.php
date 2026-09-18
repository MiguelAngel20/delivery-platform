<?php

namespace App\Http\Requests\Customer;

use App\Services\Geo\CoverageService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreCustomerAddressRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->customer !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'label' => ['required', 'string', 'max:100'],
            'address_text' => ['required', 'string', 'max:255'],
            'formatted_address' => ['nullable', 'string', 'max:500'],
            'reference' => ['nullable', 'string'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'place_id' => ['nullable', 'string', 'max:255'],
            'google_maps_url' => ['nullable', 'string'],
            'is_default' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return list<\Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->hasAny(['latitude', 'longitude'])) {
                    return;
                }

                $latitude = $this->input('latitude');
                $longitude = $this->input('longitude');

                if (! is_numeric($latitude) || ! is_numeric($longitude)) {
                    return;
                }

                $coverage = app(CoverageService::class);

                if (! $coverage->isPointCovered((float) $latitude, (float) $longitude)) {
                    $validator->errors()->add(
                        'latitude',
                        $coverage->unavailableMessage(),
                    );
                }
            },
        ];
    }
}
