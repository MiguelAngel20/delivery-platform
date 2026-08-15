<?php

namespace App\Http\Requests\Customer;

use App\Models\DriverRating;
use App\Models\Order;
use Illuminate\Foundation\Http\FormRequest;

class StoreDriverRatingRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Order $order */
        $order = $this->route('order');

        return $this->user()?->can('create', [DriverRating::class, $order]) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $star = ['nullable', 'integer', 'min:1', 'max:5'];

        return [
            'overall_rating' => ['required', 'integer', 'min:1', 'max:5'],
            'speed_rating' => $star,
            'service_rating' => $star,
            'care_rating' => $star,
            'respect_rating' => $star,
            'communication_rating' => $star,
            'comment' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
