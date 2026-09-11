<?php

namespace App\Support\Catalog;

use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

final class CatalogListPagination
{
    public const DEFAULT_PER_PAGE = 25;

    /** @var list<int> */
    public const ALLOWED_PER_PAGE = [25, 50, 75, 100];

    public static function perPage(Request $request): int
    {
        $perPage = $request->integer('per_page', self::DEFAULT_PER_PAGE);

        return in_array($perPage, self::ALLOWED_PER_PAGE, true)
            ? $perPage
            : self::DEFAULT_PER_PAGE;
    }

    /**
     * @param  Builder<Product>  $query
     * @return Builder<Product>
     */
    public static function applyProductCategoryFilters(
        Builder $query,
        mixed $categoryId,
        mixed $subcategoryId,
    ): Builder {
        if (filled($subcategoryId)) {
            return $query->where('product_category_id', (int) $subcategoryId);
        }

        if (filled($categoryId)) {
            $principalId = (int) $categoryId;

            return $query->where(function (Builder $inner) use ($principalId): void {
                $inner->where('product_category_id', $principalId)
                    ->orWhereHas(
                        'category',
                        fn (Builder $category): Builder => $category->where('parent_id', $principalId),
                    );
            });
        }

        return $query;
    }
}
