<?php

namespace App\Actions\Catalog;

use App\Models\Promotion;
use App\Models\User;
use App\Support\PromotionImageStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

final class UpdatePromotion
{
    public function __construct(
        private readonly CreatePromotion $createPromotion,
        private readonly PromotionImageStorage $imageStorage,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Promotion $promotion, array $data, ?User $actor = null): Promotion
    {
        return DB::transaction(function () use ($promotion, $data): Promotion {
            if (($data['image'] ?? null) instanceof UploadedFile) {
                $this->imageStorage->replace($promotion, $data['image']);
            }

            $promotion->update([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'promotion_price' => $data['promotion_price'],
                'starts_at' => $data['starts_at'] ?? null,
                'ends_at' => $data['ends_at'] ?? null,
                'status' => $data['status'] ?? $promotion->status,
            ]);

            if (array_key_exists('items', $data)) {
                $branch = $promotion->branch()->firstOrFail();
                $this->createPromotion->syncItems($promotion, $branch, $data['items'] ?? []);
            }

            return $promotion->fresh(['items.product']);
        });
    }
}
