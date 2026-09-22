<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreInventoryAdjustmentRequest;
use App\Http\Resources\InventoryAdjustmentResource;
use App\Models\AdjustmentReason;
use App\Models\Batch;
use App\Models\InventoryAdjustment;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryAdjustmentController extends Controller
{
    public function store(StoreInventoryAdjustmentRequest $request): JsonResponse
    {
        $adjustment = DB::transaction(function () use ($request) {
            // 加行锁，防止并发修改同一批次
            $batch = Batch::whereKey($request->batch_id)
                ->lockForUpdate()
                ->firstOrFail();

            // 在事务内重新校验原因是否仍启用且适用于库存调整
            $reason = AdjustmentReason::query()
                ->whereKey($request->adjustment_reason_id)
                ->active()
                ->forInventoryAdjustment()
                ->first();

            if ($reason === null) {
                throw ValidationException::withMessages([
                    'adjustment_reason_id' => ['The selected reason is not active or not valid for inventory adjustments.'],
                ]);
            }

            $oldQuantity = $batch->current_quantity;
            $newQuantity = (int) $request->new_quantity;
            $diff = $newQuantity - $oldQuantity;

            $adjustment = InventoryAdjustment::create([
                'batch_id'             => $batch->id,
                'adjustment_reason_id' => $reason->id,
                'old_quantity'         => $oldQuantity,
                'new_quantity'         => $newQuantity,
                'quantity_diff'        => $diff,
                'note'                 => $request->note,
            ]);

            // 安全更新批次库存
            $batch->update(['current_quantity' => $newQuantity]);

            // 预加载关联，避免 Resource 内触发 N+1
            $adjustment->load(['batch.product', 'batch.warehouse', 'adjustmentReason']);

            return $adjustment;
        });

        return (new InventoryAdjustmentResource($adjustment))
            ->response()
            ->setStatusCode(201);
    }

    public function show(InventoryAdjustment $inventoryAdjustment): JsonResponse
    {
        $inventoryAdjustment->load(['batch.product', 'batch.warehouse', 'adjustmentReason']);

        return InventoryAdjustmentResource::make($inventoryAdjustment)
            ->response()
            ->setStatusCode(200);
    }
}
