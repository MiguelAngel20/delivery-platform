<?php

namespace App\Actions\Catalog;

use App\Enums\ProductOptionGroupType;
use App\Models\BusinessBranch;
use App\Models\Product;
use App\Models\ProductOption;
use App\Models\ProductOptionCluster;
use App\Models\ProductOptionGroup;
use App\Models\User;
use App\Support\ProductImageStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CreateProduct
{
    public function __construct(
        private readonly ChangeProductPrice $changeProductPrice,
        private readonly ProductImageStorage $imageStorage,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(BusinessBranch $branch, array $data, ?User $actor = null): Product
    {
        return DB::transaction(function () use ($branch, $data, $actor): Product {
            $data = $this->applySizePricing($data);

            $imagePath = null;

            if (($data['image'] ?? null) instanceof UploadedFile) {
                $imagePath = $this->imageStorage->store($data['image']);
            } elseif (filled($data['existing_image_path'] ?? null)) {
                $imagePath = (string) $data['existing_image_path'];
            }

            $product = Product::query()->create([
                'branch_id' => $branch->id,
                'product_category_id' => $data['product_category_id'] ?? null,
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'image_path' => $imagePath,
                'is_available' => (bool) ($data['is_available'] ?? true),
                'is_active' => (bool) ($data['is_active'] ?? true),
                'allow_special_instructions' => (bool) ($data['allow_special_instructions'] ?? true),
            ]);

            $this->changeProductPrice->handle(
                $product,
                (string) $data['list_price'],
                $actor,
                isset($data['acquisition_cost']) && $data['acquisition_cost'] !== null && $data['acquisition_cost'] !== ''
                    ? (string) $data['acquisition_cost']
                    : null,
            );
            $this->syncOptionGroups($product, $data['option_groups'] ?? []);

            return $product->fresh(['currentPrice', 'optionGroups.options', 'optionGroups.clusters.options', 'category']);
        });
    }

    /**
     * When a size group is present, treat option price_modifier values as absolute prices,
     * set list_price to the minimum, and store relative modifiers.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function applySizePricing(array $data): array
    {
        if (! is_array($data['option_groups'] ?? null)) {
            return $data;
        }

        $normalizedGroups = [];
        $sizeMin = null;

        foreach (array_values($data['option_groups']) as $groupIndex => $groupData) {
            if (! is_array($groupData)) {
                continue;
            }

            $type = ProductOptionGroupType::tryFrom((string) ($groupData['type'] ?? ''));

            if ($type !== ProductOptionGroupType::Size) {
                $normalizedGroups[] = $groupData;

                continue;
            }

            $options = array_values(array_filter(
                $groupData['options'] ?? [],
                fn (mixed $option): bool => is_array($option) && filled(trim((string) ($option['name'] ?? ''))),
            ));

            if ($options === []) {
                continue;
            }

            $absolutePrices = [];

            foreach ($options as $optionIndex => $optionData) {
                $absolute = trim((string) ($optionData['price_modifier'] ?? ''));

                if ($absolute === '' || ! is_numeric($absolute) || bccomp($absolute, '0', 2) === -1) {
                    throw ValidationException::withMessages([
                        "option_groups.{$groupIndex}.options.{$optionIndex}.price_modifier" => 'Cada tamaño debe tener un precio válido mayor o igual a 0.',
                    ]);
                }

                $absolutePrices[] = number_format((float) $absolute, 2, '.', '');
            }

            $min = $absolutePrices[0];

            foreach ($absolutePrices as $absolutePrice) {
                if (bccomp($absolutePrice, $min, 2) === -1) {
                    $min = $absolutePrice;
                }
            }

            $convertedOptions = [];

            foreach ($options as $optionIndex => $optionData) {
                $absolute = $absolutePrices[$optionIndex];
                $convertedOptions[] = [
                    ...$optionData,
                    'price_modifier' => bcsub($absolute, $min, 2),
                    'is_default' => (bool) ($optionData['is_default'] ?? false),
                    'is_available' => (bool) ($optionData['is_available'] ?? true),
                ];
            }

            $normalizedGroups[] = [
                ...$groupData,
                'name' => filled(trim((string) ($groupData['name'] ?? '')))
                    ? $groupData['name']
                    : ProductOptionGroupType::Size->label(),
                'type' => ProductOptionGroupType::Size->value,
                'is_required' => true,
                'min_selection' => 1,
                'max_selection' => 1,
                'is_active' => (bool) ($groupData['is_active'] ?? true),
                'options' => $convertedOptions,
            ];

            $sizeMin = $min;
        }

        $data['option_groups'] = $normalizedGroups;

        if ($sizeMin !== null) {
            $data['list_price'] = $sizeMin;
        }

        return $data;
    }

    /**
     * @param  list<array<string, mixed>>  $groups
     */
    public function syncOptionGroups(Product $product, array $groups): void
    {
        $product->optionGroups()->each(function (ProductOptionGroup $group): void {
            $group->options()->delete();
            $group->clusters()->delete();
            $group->delete();
        });

        foreach (array_values($groups) as $index => $groupData) {
            $this->assertGroupRules($groupData);

            $type = ProductOptionGroupType::tryFrom((string) ($groupData['type'] ?? ''));
            $hasClusters = $type === ProductOptionGroupType::Choice
                && filter_var($groupData['has_option_clusters'] ?? false, FILTER_VALIDATE_BOOLEAN);

            $group = ProductOptionGroup::query()->create([
                'product_id' => $product->id,
                'name' => (string) ($groupData['name'] ?? ''),
                'type' => $groupData['type'] ?? ProductOptionGroupType::Choice->value,
                'is_required' => (bool) ($groupData['is_required'] ?? false),
                'min_selection' => (int) ($groupData['min_selection'] ?? 0),
                'max_selection' => (int) ($groupData['max_selection'] ?? 1),
                'sort_order' => (int) ($groupData['sort_order'] ?? $index),
                'is_active' => (bool) ($groupData['is_active'] ?? true),
                'has_option_clusters' => $hasClusters,
            ]);

            if ($hasClusters) {
                $this->syncClusteredOptions($group, $groupData['clusters'] ?? []);

                continue;
            }

            foreach (array_values($groupData['options'] ?? []) as $optionIndex => $optionData) {
                if (! is_array($optionData) || blank($optionData['name'] ?? null)) {
                    continue;
                }

                ProductOption::query()->create([
                    'option_group_id' => $group->id,
                    'option_cluster_id' => null,
                    'name' => (string) $optionData['name'],
                    'description' => $optionData['description'] ?? null,
                    'price_modifier' => $optionData['price_modifier'] ?? 0,
                    'is_default' => (bool) ($optionData['is_default'] ?? false),
                    'is_available' => (bool) ($optionData['is_available'] ?? true),
                    'sort_order' => (int) ($optionData['sort_order'] ?? $optionIndex),
                ]);
            }
        }
    }

    /**
     * @param  list<array<string, mixed>>  $clusters
     */
    private function syncClusteredOptions(ProductOptionGroup $group, array $clusters): void
    {
        $optionSort = 0;

        foreach (array_values($clusters) as $clusterIndex => $clusterData) {
            if (! is_array($clusterData) || blank($clusterData['name'] ?? null)) {
                continue;
            }

            $cluster = ProductOptionCluster::query()->create([
                'option_group_id' => $group->id,
                'name' => (string) $clusterData['name'],
                'sort_order' => (int) ($clusterData['sort_order'] ?? $clusterIndex),
            ]);

            foreach (array_values($clusterData['options'] ?? []) as $optionData) {
                if (! is_array($optionData) || blank($optionData['name'] ?? null)) {
                    continue;
                }

                ProductOption::query()->create([
                    'option_group_id' => $group->id,
                    'option_cluster_id' => $cluster->id,
                    'name' => (string) $optionData['name'],
                    'description' => $optionData['description'] ?? null,
                    'price_modifier' => $optionData['price_modifier'] ?? 0,
                    'is_default' => (bool) ($optionData['is_default'] ?? false),
                    'is_available' => (bool) ($optionData['is_available'] ?? true),
                    'sort_order' => (int) ($optionData['sort_order'] ?? $optionSort),
                ]);

                $optionSort++;
            }
        }
    }

    /**
     * @param  array<string, mixed>  $groupData
     */
    private function assertGroupRules(array $groupData): void
    {
        $min = (int) ($groupData['min_selection'] ?? 0);
        $max = (int) ($groupData['max_selection'] ?? 1);

        if ($min < 0 || $max < $min) {
            throw ValidationException::withMessages([
                'option_groups' => 'Cada grupo debe cumplir min_selection >= 0 y max_selection >= min_selection.',
            ]);
        }

        $type = ProductOptionGroupType::tryFrom((string) ($groupData['type'] ?? ''));

        if ($type === ProductOptionGroupType::Removable && ($groupData['is_required'] ?? false)) {
            throw ValidationException::withMessages([
                'option_groups' => 'Los grupos REMOVABLE no deben ser obligatorios.',
            ]);
        }
    }
}
