<?php

namespace Database\Factories;

use App\Enums\OptionSelectionAction;
use App\Enums\ProductOptionGroupType;
use App\Models\OrderItem;
use App\Models\OrderItemOption;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderItemOption>
 */
class OrderItemOptionFactory extends Factory
{
    protected $model = OrderItemOption::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_item_id' => OrderItem::factory(),
            'product_option_id' => null,
            'option_name' => 'Opción',
            'option_type' => ProductOptionGroupType::Choice,
            'price_modifier' => 0,
            'selection_action' => OptionSelectionAction::Selected,
            'created_at' => now(),
        ];
    }
}
