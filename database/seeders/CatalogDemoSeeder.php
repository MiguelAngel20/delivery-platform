<?php

namespace Database\Seeders;

use App\Enums\BranchStatus;
use App\Enums\BusinessDeliveryMode;
use App\Enums\BusinessOperationMode;
use App\Enums\BusinessStatus;
use App\Enums\ProductOptionGroupType;
use App\Enums\PromotionStatus;
use App\Models\Business;
use App\Models\BusinessBranch;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductOption;
use App\Models\ProductOptionGroup;
use App\Models\ProductPrice;
use App\Models\Promotion;
use App\Models\PromotionItem;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Catálogo demo (solo desarrollo). Idempotente sobre Pollo Güero Demo.
 */
class CatalogDemoSeeder extends Seeder
{
    public function run(): void
    {
        $business = Business::query()->where('slug', 'pollo-guero-demo')->first();

        if ($business === null) {
            return;
        }

        $branch = $business->branches()->where('name', 'Sucursal Centro')->first();

        if ($branch === null) {
            return;
        }

        $admin = User::query()->where('email', 'admin@ride.test')->first();

        $category = ProductCategory::query()->updateOrCreate(
            [
                'branch_id' => $branch->id,
                'name' => 'Hamburguesas',
            ],
            [
                'description' => 'Clásicas y especiales',
                'sort_order' => 1,
                'is_active' => true,
            ],
        );

        $wingsCategory = ProductCategory::query()->updateOrCreate(
            [
                'branch_id' => $branch->id,
                'name' => 'Alitas',
            ],
            [
                'description' => 'Porciones con salsa a elegir',
                'sort_order' => 2,
                'is_active' => true,
            ],
        );

        $burger = Product::query()->updateOrCreate(
            [
                'branch_id' => $branch->id,
                'name' => 'Hamburguesa clásica',
            ],
            [
                'product_category_id' => $category->id,
                'description' => 'Carne, pan y vegetales. Personaliza ingredientes y extras.',
                'is_available' => true,
                'is_active' => true,
                'allow_special_instructions' => true,
            ],
        );

        $this->ensureActivePrice($burger, '105.00', $admin?->id);
        $this->replaceGroups($burger, [
            [
                'name' => 'Ingredientes',
                'type' => ProductOptionGroupType::Removable,
                'is_required' => false,
                'min_selection' => 0,
                'max_selection' => 10,
                'options' => [
                    ['name' => 'Lechuga', 'is_default' => true, 'price_modifier' => 0],
                    ['name' => 'Tomate', 'is_default' => true, 'price_modifier' => 0],
                    ['name' => 'Cebolla', 'is_default' => true, 'price_modifier' => 0],
                ],
            ],
            [
                'name' => 'Extras',
                'type' => ProductOptionGroupType::Addon,
                'is_required' => false,
                'min_selection' => 0,
                'max_selection' => 5,
                'options' => [
                    ['name' => 'Queso extra', 'is_default' => false, 'price_modifier' => 15],
                    ['name' => 'Tocino', 'is_default' => false, 'price_modifier' => 20],
                ],
            ],
        ]);

        $wings = Product::query()->updateOrCreate(
            [
                'branch_id' => $branch->id,
                'name' => 'Alitas',
            ],
            [
                'product_category_id' => $wingsCategory->id,
                'description' => 'Elige tu salsa. No incluye ingredientes removibles internos.',
                'is_available' => true,
                'is_active' => true,
                'allow_special_instructions' => true,
            ],
        );

        $this->ensureActivePrice($wings, '120.00', $admin?->id);
        $this->replaceGroups($wings, [
            [
                'name' => 'Salsa',
                'type' => ProductOptionGroupType::Choice,
                'is_required' => true,
                'min_selection' => 1,
                'max_selection' => 1,
                'options' => [
                    ['name' => 'BBQ', 'is_default' => true, 'price_modifier' => 0],
                    ['name' => 'Búfalo', 'is_default' => false, 'price_modifier' => 0],
                    ['name' => 'Mango habanero', 'is_default' => false, 'price_modifier' => 0],
                ],
            ],
        ]);

        $promotion = Promotion::query()->updateOrCreate(
            [
                'branch_id' => $branch->id,
                'name' => 'Hamburguesa + Jugo',
            ],
            [
                'description' => 'Combo con jugo externo al menú.',
                'promotion_price' => '120.00',
                'starts_at' => now()->subDay(),
                'ends_at' => now()->addMonth(),
                'status' => PromotionStatus::Active,
                'created_by_user_id' => $admin?->id,
            ],
        );

        $promotion->items()->delete();

        PromotionItem::query()->create([
            'promotion_id' => $promotion->id,
            'product_id' => $burger->id,
            'name' => $burger->name,
            'quantity' => 1,
            'original_price' => '105.00',
            'is_external_item' => false,
        ]);

        PromotionItem::query()->create([
            'promotion_id' => $promotion->id,
            'product_id' => null,
            'name' => 'Jugo',
            'quantity' => 1,
            'original_price' => null,
            'is_external_item' => true,
        ]);

        $this->seedPlatformOperated($admin);
    }

    private function seedPlatformOperated(?User $admin): void
    {
        $business = Business::query()->updateOrCreate(
            ['slug' => 'ride-kitchen-demo'],
            [
                'name' => 'RIDE Kitchen Demo',
                'description' => 'Empresa PLATFORM_OPERATED para catálogo Admin.',
                'business_type' => 'Restaurante',
                'operation_mode' => BusinessOperationMode::PlatformOperated,
                'delivery_mode' => BusinessDeliveryMode::PlatformDrivers,
                'status' => BusinessStatus::Active,
                'phone' => '+50255552000',
                'email' => 'kitchen@ride.test',
                'created_by_user_id' => $admin?->id,
                'approved_by_user_id' => $admin?->id,
                'approved_at' => now(),
            ],
        );

        BusinessBranch::query()->updateOrCreate(
            [
                'business_id' => $business->id,
                'name' => 'Cocina Central',
            ],
            [
                'phone' => '+50255552001',
                'address_text' => 'Zona 10, Ciudad de Guatemala',
                'reference' => null,
                'latitude' => '14.6000000',
                'longitude' => '-90.5100000',
                'status' => BranchStatus::Active,
            ],
        );

        $business->limits()->updateOrCreate(
            ['business_id' => $business->id],
            [
                'max_branches' => 3,
                'max_business_admins' => 2,
                'max_employees_per_branch' => 5,
            ],
        );
    }

    private function ensureActivePrice(Product $product, string $price, ?int $userId): void
    {
        $active = $product->prices()->where('is_active', true)->first();

        if ($active !== null && bccomp((string) $active->list_price, $price, 2) === 0) {
            return;
        }

        $product->prices()->where('is_active', true)->update([
            'is_active' => false,
            'valid_until' => now(),
        ]);

        ProductPrice::query()->create([
            'product_id' => $product->id,
            'list_price' => $price,
            'valid_from' => now(),
            'is_active' => true,
            'created_by_user_id' => $userId,
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $groups
     */
    private function replaceGroups(Product $product, array $groups): void
    {
        $product->optionGroups()->each(function (ProductOptionGroup $group): void {
            $group->options()->delete();
            $group->delete();
        });

        foreach ($groups as $index => $groupData) {
            $group = ProductOptionGroup::query()->create([
                'product_id' => $product->id,
                'name' => $groupData['name'],
                'type' => $groupData['type'],
                'is_required' => $groupData['is_required'],
                'min_selection' => $groupData['min_selection'],
                'max_selection' => $groupData['max_selection'],
                'sort_order' => $index,
                'is_active' => true,
            ]);

            foreach ($groupData['options'] as $optionIndex => $optionData) {
                ProductOption::query()->create([
                    'option_group_id' => $group->id,
                    'name' => $optionData['name'],
                    'price_modifier' => $optionData['price_modifier'],
                    'is_default' => $optionData['is_default'],
                    'is_available' => true,
                    'sort_order' => $optionIndex,
                ]);
            }
        }
    }
}
