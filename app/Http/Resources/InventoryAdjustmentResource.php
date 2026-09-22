<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\InventoryAdjustment
 */
class InventoryAdjustmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'old_quantity'  => $this->old_quantity,
            'new_quantity'  => $this->new_quantity,
            'quantity_diff' => $this->quantity_diff,
            'note'          => $this->note,
            'created_at'    => $this->created_at?->toIso8601String(),
            'batch' => [
                'id'               => $this->batch->id,
                'batch_no'         => $this->batch->batch_no,
                'current_quantity' => $this->batch->current_quantity,
                'product' => [
                    'id'   => $this->batch->product->id,
                    'name' => $this->batch->product->name,
                    'sku'  => $this->batch->product->sku,
                ],
                'warehouse' => [
                    'id'   => $this->batch->warehouse->id,
                    'name' => $this->batch->warehouse->name,
                    'code' => $this->batch->warehouse->code,
                ],
            ],
            'reason' => [
                'id'   => $this->adjustmentReason->id,
                'code' => $this->adjustmentReason->code,
                'name' => $this->adjustmentReason->name,
            ],
        ];
    }
}
