<?php

namespace Database\Seeders;

use App\Models\AdjustmentReason;
use Illuminate\Database\Seeder;

class AdjustmentReasonSeeder extends Seeder
{
    public function run(): void
    {
        $reasons = [
            ['code' => 'physical_count', 'name' => '盘点修正',     'is_active' => true],
            ['code' => 'damaged',         'name' => '商品损坏',     'is_active' => true],
            ['code' => 'missing',        'name' => '商品丢失',     'is_active' => true],
            ['code' => 'data_entry',     'name' => '数据录入修正', 'is_active' => true],
            ['code' => 'expired_demo',   'name' => '已停用原因示例', 'is_active' => false],
        ];

        foreach ($reasons as $reason) {
            AdjustmentReason::updateOrCreate(
                ['code' => $reason['code']],
                [
                    'name'      => $reason['name'],
                    'is_active' => $reason['is_active'],
                    'applies_to' => AdjustmentReason::APPLIES_TO_INVENTORY_ADJUSTMENT,
                ]
            );
        }
    }
}
