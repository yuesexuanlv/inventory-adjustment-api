<?php

namespace Database\Seeders;

use App\Models\Batch;
use App\Models\Product;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;

class BatchSeeder extends Seeder
{
    public function run(): void
    {
        $mainWarehouse = Warehouse::where('code', 'MAIN')->firstOrFail();
        $waterProduct  = Product::where('sku', 'SKU-WATER-550')->firstOrFail();
        $noodleProduct = Product::where('sku', 'SKU-NOODLE-BR')->firstOrFail();

        // 批次 1：初始库存 100，用于演示题目例子（100 -> 92）
        Batch::updateOrCreate(
            ['batch_no' => 'B20260922-001'],
            [
                'warehouse_id'     => $mainWarehouse->id,
                'product_id'       => $waterProduct->id,
                'current_quantity' => 100,
            ]
        );

        // 批次 2：初始库存 50
        Batch::updateOrCreate(
            ['batch_no' => 'B20260922-002'],
            [
                'warehouse_id'     => $mainWarehouse->id,
                'product_id'       => $noodleProduct->id,
                'current_quantity' => 50,
            ]
        );
    }
}
