<?php

namespace App\Http\Controllers;

use App\Http\Resources\AdjustmentReasonResource;
use App\Models\AdjustmentReason;

class AdjustmentReasonController extends Controller
{
    public function index()
    {
        $reasons = AdjustmentReason::query()
            ->active()
            ->forInventoryAdjustment()
            ->orderBy('id')
            ->get();

        return AdjustmentReasonResource::collection($reasons);
    }
}
