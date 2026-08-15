<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * @deprecated Use DemoDomainSeeder. Kept as a thin alias for existing docs/commands.
 */
class DemoUsersSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(DemoDomainSeeder::class);
    }
}
