<?php

namespace App\Actions\Catalog;

use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class ChangeProductPrice
{
    public function handle(Product $product, string $listPrice, ?User $actor = null, ?string $acquisitionCost = null): ProductPrice
    {
        return DB::transaction(function () use ($product, $listPrice, $actor, $acquisitionCost): ProductPrice {
            $locked = Product::query()->whereKey($product->id)->lockForUpdate()->firstOrFail();

            $now = now();

            $previous = ProductPrice::query()
                ->where('product_id', $locked->id)
                ->where('is_active', true)
                ->lockForUpdate()
                ->get();

            $inheritedCost = $previous->first()?->acquisition_cost;

            $previous->each(function (ProductPrice $price) use ($now): void {
                $price->update([
                    'is_active' => false,
                    'valid_until' => $now,
                ]);
            });

            return ProductPrice::query()->create([
                'product_id' => $locked->id,
                'list_price' => $listPrice,
                'acquisition_cost' => $acquisitionCost ?? $inheritedCost,
                'valid_from' => $now,
                'valid_until' => null,
                'is_active' => true,
                'created_by_user_id' => $actor?->id,
            ]);
        });
    }
}
