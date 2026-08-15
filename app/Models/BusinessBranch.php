<?php

namespace App\Models;

use App\Enums\BranchStatus;
use Database\Factories\BusinessBranchFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $business_id
 * @property string $name
 * @property string|null $phone
 * @property string $address_text
 * @property string|null $formatted_address
 * @property string|null $reference
 * @property string $latitude
 * @property string $longitude
 * @property string|null $place_id
 * @property string|null $google_maps_url
 * @property BranchStatus $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
#[Fillable([
    'business_id',
    'name',
    'phone',
    'address_text',
    'formatted_address',
    'reference',
    'latitude',
    'longitude',
    'place_id',
    'google_maps_url',
    'status',
])]
class BusinessBranch extends Model
{
    /** @use HasFactory<BusinessBranchFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => BranchStatus::class,
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function businessUsers(): BelongsToMany
    {
        return $this->belongsToMany(
            BusinessUser::class,
            'business_user_branches',
            'branch_id',
            'business_user_id',
        )->withTimestamps();
    }

    public function productCategories(): HasMany
    {
        return $this->hasMany(ProductCategory::class, 'branch_id');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'branch_id');
    }

    public function promotions(): HasMany
    {
        return $this->hasMany(Promotion::class, 'branch_id');
    }

    public function pricingRules(): HasMany
    {
        return $this->hasMany(PricingRule::class, 'branch_id');
    }
}
