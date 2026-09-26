<?php

namespace App\Actions\Catalog;

use App\Models\Product;
use App\Models\User;
use App\Support\ProductImageStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

final class UpdateProduct
{
    public function __construct(
        private readonly CreateProduct $createProduct,
        private readonly ChangeProductPrice $changeProductPrice,
        private readonly ProductImageStorage $imageStorage,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Product $product, array $data, ?User $actor = null): Product
    {
        return DB::transaction(function () use ($product, $data, $actor): Product {
            $data = $this->createProduct->applySizePricing($data);

            if (($data['image'] ?? null) instanceof UploadedFile) {
                $this->imageStorage->replace($product, $data['image']);
            } elseif (
                array_key_exists('existing_image_path', $data)
                && filled($data['existing_image_path'])
                && $data['existing_image_path'] !== $product->image_path
            ) {
                $previousPath = $product->image_path;
                $product->forceFill([
                    'image_path' => (string) $data['existing_image_path'],
                ])->save();

                if (is_string($previousPath) && $previousPath !== '') {
                    $this->imageStorage->deleteIfUnused($previousPath);
                }
            }

            $product->update([
                'product_category_id' => $data['product_category_id'] ?? null,
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'is_available' => (bool) ($data['is_available'] ?? $product->is_available),
                'is_active' => (bool) ($data['is_active'] ?? $product->is_active),
                'allow_special_instructions' => (bool) ($data['allow_special_instructions'] ?? $product->allow_special_instructions),
            ]);

            if (array_key_exists('list_price', $data) && $data['list_price'] !== null) {
                $product->loadMissing('currentPrice');
                $current = $product->currentPrice?->list_price;
                $currentCost = $product->currentPrice?->acquisition_cost;
                $nextCost = array_key_exists('acquisition_cost', $data)
                    ? ($data['acquisition_cost'] !== null && $data['acquisition_cost'] !== ''
                        ? (string) $data['acquisition_cost']
                        : null)
                    : ($currentCost !== null ? (string) $currentCost : null);

                $listChanged = $current === null || bccomp((string) $current, (string) $data['list_price'], 2) !== 0;
                $costChanged = ($currentCost === null ? null : (string) $currentCost) !== $nextCost;

                if ($listChanged || $costChanged) {
                    $this->changeProductPrice->handle(
                        $product,
                        (string) $data['list_price'],
                        $actor,
                        $nextCost,
                    );
                }
            }

            if (array_key_exists('option_groups', $data)) {
                $this->createProduct->syncOptionGroups($product, $data['option_groups'] ?? []);
            }

            return $product->fresh(['currentPrice', 'optionGroups.options', 'optionGroups.clusters.options', 'category']);
        });
    }
}
