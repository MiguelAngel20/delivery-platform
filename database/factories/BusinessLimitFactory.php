<?php

namespace Database\Factories;

use App\Models\Business;
use App\Models\BusinessLimit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BusinessLimit>
 */
class BusinessLimitFactory extends Factory
{
    protected $model = BusinessLimit::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            'max_branches' => (int) config('business.defaults.max_branches'),
            'max_business_admins' => (int) config('business.defaults.max_business_admins'),
            'max_employees_per_branch' => (int) config('business.defaults.max_employees_per_branch'),
        ];
    }
}
