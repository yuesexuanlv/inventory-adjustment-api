<?php

namespace Database\Seeders;

use App\Models\Warehouse;
use Illuminate\Database\Seeder;

class WarehouseSeeder extends Seeder
{
    public function run(): void
    {
        Warehouse::updateOrCreate(
            ['code' => 'MAIN'],
            ['name' => '主仓库']
        );

        Warehouse::updateOrCreate(
            ['code' => 'SECOND'],
            ['name' => '副仓库']
        );
    }
}
