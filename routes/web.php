<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'message' => 'Inventory Adjustment API',
        'docs'    => '/api/adjustment-reasons',
    ]);
});
