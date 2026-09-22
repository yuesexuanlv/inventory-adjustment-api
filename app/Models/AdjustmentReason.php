<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'code', 'is_active', 'applies_to'])]
class AdjustmentReason extends Model
{
    /** @use HasFactory<\Database\Factories\AdjustmentReasonFactory> */
    use HasFactory;

    public const APPLIES_TO_INVENTORY_ADJUSTMENT = 'inventory_adjustment';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * 只取启用状态的原因。
     *
     * @param  Builder<AdjustmentReason>  $query
     * @return Builder<AdjustmentReason>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * 只取适用于库存调整场景的原因。
     *
     * @param  Builder<AdjustmentReason>  $query
     * @return Builder<AdjustmentReason>
     */
    public function scopeForInventoryAdjustment(Builder $query): Builder
    {
        return $query->where('applies_to', self::APPLIES_TO_INVENTORY_ADJUSTMENT);
    }

    /**
     * @return HasMany<InventoryAdjustment>
     */
    public function inventoryAdjustments(): HasMany
    {
        return $this->hasMany(InventoryAdjustment::class);
    }
}
