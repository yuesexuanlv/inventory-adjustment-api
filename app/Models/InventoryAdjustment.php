<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['batch_id', 'adjustment_reason_id', 'old_quantity', 'new_quantity', 'quantity_diff', 'note'])]
class InventoryAdjustment extends Model
{
    /** @use HasFactory<\Database\Factories\InventoryAdjustmentFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'old_quantity'   => 'integer',
            'new_quantity'   => 'integer',
            'quantity_diff'  => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Batch, InventoryAdjustment>
     */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    /**
     * @return BelongsTo<AdjustmentReason, InventoryAdjustment>
     */
    public function adjustmentReason(): BelongsTo
    {
        return $this->belongsTo(AdjustmentReason::class);
    }
}
