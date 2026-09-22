<?php

namespace Database\Seeders;

use App\Models\AdjustmentReason;
use Illuminate\Database\Seeder;

class AdjustmentReasonSeeder extends Seeder
{
    public function run(): void
    {
        $reasons = [
            ['code' => 'physical_count', 'name' => '盘点修正',     'is_active' => true,  'applies_to' => AdjustmentReason::APPLIES_TO_INVENTORY_ADJUSTMENT],
            ['code' => 'damaged',         'name' => '商品损坏',     'is_active' => true,  'applies_to' => AdjustmentReason::APPLIES_TO_INVENTORY_ADJUSTMENT],
            ['code' => 'missing',        'name' => '商品丢失',     'is_active' => true,  'applies_to' => AdjustmentReason::APPLIES_TO_INVENTORY_ADJUSTMENT],
            ['code' => 'data_entry',     'name' => '数据录入修正', 'is_active' => true,  'applies_to' => AdjustmentReason::APPLIES_TO_INVENTORY_ADJUSTMENT],
            ['code' => 'expired_demo',   'name' => '已停用原因示例', 'is_active' => false, 'applies_to' => AdjustmentReason::APPLIES_TO_INVENTORY_ADJUSTMENT],
            // 启用但不适用于库存调整（用于测试 applies_to 校验）
            ['code' => 'price_adjust',   'name' => '价格调整',     'is_active' => true,  'applies_to' => 'price_adjustment'],
        ];

        foreach ($reasons as $reason) {
            AdjustmentReason::updateOrCreate(
                ['code' => $reason['code']],
                [
                    'name'       => $reason['name'],
                    'is_active'  => $reason['is_active'],
                    'applies_to' => $reason['applies_to'],
                ]
            );
        }
    }
}
