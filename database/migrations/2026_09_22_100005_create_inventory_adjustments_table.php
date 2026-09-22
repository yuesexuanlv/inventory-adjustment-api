<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('inventory_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')
                ->constrained()
                ->restrictOnDelete();
            $table->foreignId('adjustment_reason_id')
                ->constrained()
                ->restrictOnDelete();
            $table->integer('old_quantity');
            $table->integer('new_quantity');
            $table->integer('quantity_diff'); // new_quantity - old_quantity，正数=盘盈，负数=盘亏
            $table->text('note')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_adjustments');
    }
};
