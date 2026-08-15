<?php

namespace App\Actions\Catalog;

use App\Enums\PromotionStatus;
use App\Models\BusinessBranch;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\PromotionItem;
use App\Models\User;
use App\Support\PromotionImageStorage;
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

            $promotion = Promotion::query()->create([
                'branch_id' => $branch->id,
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'promotion_price' => $data['promotion_price'],
                'image_path' => $imagePath,
                'starts_at' => $data['starts_at'] ?? null,
                'ends_at' => $data['ends_at'] ?? null,
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
            ]);
        }
    }
}
