<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        Product::updateOrCreate(
            ['sku' => 'SKU-WATER-550'],
            ['name' => '矿泉水550ml']
        );

        Product::updateOrCreate(
            ['sku' => 'SKU-NOODLE-BR'],
            ['name' => '红烧牛肉面']
        );
    }
}
