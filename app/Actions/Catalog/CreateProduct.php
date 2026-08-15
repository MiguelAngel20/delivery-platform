<?php

namespace App\Actions\Catalog;

use App\Enums\ProductOptionGroupType;
use App\Models\BusinessBranch;
use App\Models\Product;
use App\Models\ProductOption;
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
            $imagePath = null;

            if (($data['image'] ?? null) instanceof UploadedFile) {
                $imagePath = $this->imageStorage->store($data['image']);
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

            return $product->fresh(['currentPrice', 'optionGroups.options', 'category']);
        });
    }

    /**
     * @param  list<array<string, mixed>>  $groups
     */
    public function syncOptionGroups(Product $product, array $groups): void
    {
        $product->optionGroups()->each(function (ProductOptionGroup $group): void {
            $group->options()->delete();
            $group->delete();
        });

        foreach (array_values($groups) as $index => $groupData) {
            $this->assertGroupRules($groupData);

            $group = ProductOptionGroup::query()->create([
                'product_id' => $product->id,
                'name' => $groupData['name'],
                'type' => $groupData['type'],
                'is_required' => (bool) ($groupData['is_required'] ?? false),
                'min_selection' => (int) ($groupData['min_selection'] ?? 0),
                'max_selection' => (int) ($groupData['max_selection'] ?? 1),
                'sort_order' => (int) ($groupData['sort_order'] ?? $index),
                'is_active' => (bool) ($groupData['is_active'] ?? true),
            ]);

            foreach (array_values($groupData['options'] ?? []) as $optionIndex => $optionData) {
                ProductOption::query()->create([
                    'option_group_id' => $group->id,
                    'name' => $optionData['name'],
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
