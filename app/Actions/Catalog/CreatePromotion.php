<?php

namespace App\Actions\Catalog;

use App\Enums\ProductOptionGroupType;
use App\Enums\PromotionStatus;
use App\Models\BusinessBranch;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\PromotionItem;
use App\Models\User;
use App\Support\Catalog\PromotionItemOptionGroups;
use App\Support\PromotionImageStorage;
use App\Support\PromotionSchedule;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CreatePromotion
{
    public function __construct(
        private readonly PromotionImageStorage $imageStorage,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(BusinessBranch $branch, array $data, ?User $actor = null): Promotion
    {
        return DB::transaction(function () use ($branch, $data, $actor): Promotion {
            $imagePath = null;

            if (($data['image'] ?? null) instanceof UploadedFile) {
                $imagePath = $this->imageStorage->store($data['image']);
            }

            $schedule = PromotionSchedule::attributesFromValidated($data);
            $data = $this->applySizePricing($data);

            $promotion = Promotion::query()->create([
                'branch_id' => $branch->id,
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'promotion_price' => $data['promotion_price'],
                'option_groups' => PromotionItemOptionGroups::sanitize(
                    is_array($data['option_groups'] ?? null) ? $data['option_groups'] : null,
                ),
                'image_path' => $imagePath,
                ...$schedule,
                'status' => $data['status'] ?? PromotionStatus::Draft->value,
                'created_by_user_id' => $actor?->id,
            ]);

            $this->syncItems($promotion, $branch, $data['items'] ?? []);

            return $promotion->fresh(['items.product']);
        });
    }

    /**
     * @param  list<array<string, mixed>>  $items
     */
    public function syncItems(Promotion $promotion, BusinessBranch $branch, array $items): void
    {
        $promotion->items()->delete();

        foreach ($items as $item) {
            $isExternal = (bool) ($item['is_external_item'] ?? false);

            if ($isExternal) {
                if (blank($item['name'] ?? null)) {
                    throw ValidationException::withMessages([
                        'items' => 'Los ítems externos requieren un nombre.',
                    ]);
                }

                PromotionItem::query()->create([
                    'promotion_id' => $promotion->id,
                    'product_id' => null,
                    'name' => $item['name'],
                    'description' => $item['description'] ?? null,
                    'quantity' => $item['quantity'] ?? 1,
                    'original_price' => $item['original_price'] ?? null,
                    'is_external_item' => true,
                    'option_groups' => PromotionItemOptionGroups::sanitize($item['option_groups'] ?? null),
                ]);

                continue;
            }

            $productId = $item['product_id'] ?? null;
            $product = Product::query()
                ->whereKey($productId)
                ->where('branch_id', $branch->id)
                ->first();

            if ($product === null) {
                throw ValidationException::withMessages([
                    'items' => 'Cada producto de promoción debe pertenecer a la misma sucursal.',
                ]);
            }

            PromotionItem::query()->create([
                'promotion_id' => $promotion->id,
                'product_id' => $product->id,
                'name' => $item['name'] ?? $product->name,
                'description' => $item['description'] ?? $product->description,
                'quantity' => $item['quantity'] ?? 1,
                'original_price' => $item['original_price'] ?? $product->listPrice(),
                'is_external_item' => false,
                'option_groups' => null,
            ]);
        }
    }

    /**
     * When a size group is present, treat option prices as absolute amounts,
     * set the promotional price to the cheapest size, and store the difference.
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
            $data['promotion_price'] = $sizeMin;
        }

        return $data;
    }
}
