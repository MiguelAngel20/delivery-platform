<?php

namespace Database\Seeders;

use App\Enums\BusinessTypeStatus;
use App\Models\BusinessType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class BusinessTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            ['name' => 'Restaurante', 'description' => 'Comida servida en mesa o para llevar.'],
            ['name' => 'Comida rápida', 'description' => 'Preparación rápida y delivery ágil.'],
        ];

        foreach ($types as $index => $type) {
            BusinessType::query()->updateOrCreate(
                ['slug' => Str::slug($type['name'])],
                [
                    'name' => $type['name'],
                    'description' => $type['description'],
                    'status' => BusinessTypeStatus::Active,
                    'sort_order' => $index + 1,
                ],
            );
        }
    }
}
