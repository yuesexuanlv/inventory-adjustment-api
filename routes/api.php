<?php

use App\Http\Controllers\AdjustmentReasonController;
use App\Http\Controllers\InventoryAdjustmentController;
use Illuminate\Support\Facades\Route;

// 预设调整原因列表（仅返回启用且适用于库存调整的原因）
Route::get('/adjustment-reasons', [AdjustmentReasonController::class, 'index']);

// 库存调整单
Route::post('/inventory-adjustments', [InventoryAdjustmentController::class, 'store']);
Route::get('/inventory-adjustments/{inventoryAdjustment}', [InventoryAdjustmentController::class, 'show']);
