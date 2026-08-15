<?php

namespace App\Models;

use Database\Factories\BusinessLimitFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $business_id
 * @property int $max_branches
 * @property int $max_business_admins
 * @property int $max_employees_per_branch
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'business_id',
    'max_branches',
    'max_business_admins',
    'max_employees_per_branch',
])]
class BusinessLimit extends Model
{
    /** @use HasFactory<BusinessLimitFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'max_branches' => 'integer',
            'max_business_admins' => 'integer',
            'max_employees_per_branch' => 'integer',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }
}
