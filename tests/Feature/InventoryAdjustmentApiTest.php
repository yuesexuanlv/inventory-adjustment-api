<?php

namespace Tests\Feature;

use App\Models\AdjustmentReason;
use App\Models\Batch;
use App\Models\Product;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryAdjustmentApiTest extends TestCase
{
    use RefreshDatabase;

    private Warehouse $warehouse;
    private Product $product;
    private Batch $batch;
    private Batch $batch2;
    private AdjustmentReason $activeReason;
    private AdjustmentReason $inactiveReason;

    protected function setUp(): void
    {
        parent::setUp();

        $this->warehouse = Warehouse::create(['name' => 'Main', 'code' => 'MAIN']);
        $this->product   = Product::create(['name' => 'Water', 'sku' => 'SKU-WATER']);

        $this->batch = Batch::create([
            'warehouse_id'     => $this->warehouse->id,
            'product_id'       => $this->product->id,
            'batch_no'         => 'B-001',
            'current_quantity' => 100,
        ]);

        $this->batch2 = Batch::create([
            'warehouse_id'     => $this->warehouse->id,
            'product_id'       => $this->product->id,
            'batch_no'         => 'B-002',
            'current_quantity' => 50,
        ]);

        $this->activeReason = AdjustmentReason::create([
            'name'       => 'Physical count',
            'code'       => 'physical_count',
            'is_active'  => true,
            'applies_to' => AdjustmentReason::APPLIES_TO_INVENTORY_ADJUSTMENT,
        ]);

        $this->inactiveReason = AdjustmentReason::create([
            'name'       => 'Deprecated',
            'code'       => 'deprecated',
            'is_active'  => false,
            'applies_to' => AdjustmentReason::APPLIES_TO_INVENTORY_ADJUSTMENT,
        ]);
    }

    public function test_lists_active_reasons_only(): void
    {
        $response = $this->getJson('/api/adjustment-reasons');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.code', 'physical_count');
    }

    public function test_creates_adjustment_and_updates_batch_quantity(): void
    {
        $response = $this->postJson('/api/inventory-adjustments', [
            'batch_id'             => $this->batch->id,
            'adjustment_reason_id' => $this->activeReason->id,
            'new_quantity'         => 92,
            'note'                 => 'stocktake found 8 missing',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.old_quantity', 100)
            ->assertJsonPath('data.new_quantity', 92)
            ->assertJsonPath('data.quantity_diff', -8)
            ->assertJsonPath('data.batch.current_quantity', 92)
            ->assertJsonPath('data.reason.code', 'physical_count');

        // DB must reflect the update
        $this->assertDatabaseHas('batches', [
            'id'               => $this->batch->id,
            'current_quantity'  => 92,
        ]);

        $this->assertDatabaseHas('inventory_adjustments', [
            'batch_id'             => $this->batch->id,
            'adjustment_reason_id' => $this->activeReason->id,
            'old_quantity'         => 100,
            'new_quantity'         => 92,
            'quantity_diff'        => -8,
        ]);
    }

    public function test_rejects_inactive_reason_with_422(): void
    {
        $response = $this->postJson('/api/inventory-adjustments', [
            'batch_id'             => $this->batch2->id,
            'adjustment_reason_id' => $this->inactiveReason->id,
            'new_quantity'         => 40,
        ]);

        $response->assertStatus(422);

        // batch 2 must remain unchanged
        $this->assertDatabaseHas('batches', [
            'id'               => $this->batch2->id,
            'current_quantity' => 50,
        ]);
    }

    public function test_rejects_nonexistent_batch_with_422(): void
    {
        $response = $this->postJson('/api/inventory-adjustments', [
            'batch_id'             => 9999,
            'adjustment_reason_id' => $this->activeReason->id,
            'new_quantity'         => 50,
        ]);

        $response->assertStatus(422);
    }

    public function test_rejects_negative_quantity_with_422(): void
    {
        $response = $this->postJson('/api/inventory-adjustments', [
            'batch_id'             => $this->batch2->id,
            'adjustment_reason_id' => $this->activeReason->id,
            'new_quantity'         => -5,
        ]);

        $response->assertStatus(422);
    }

    public function test_missing_new_quantity_is_rejected(): void
    {
        $response = $this->postJson('/api/inventory-adjustments', [
            'batch_id'             => $this->batch2->id,
            'adjustment_reason_id' => $this->activeReason->id,
        ]);

        $response->assertStatus(422);
    }

    public function test_returns_404_for_nonexistent_adjustment(): void
    {
        $response = $this->getJson('/api/inventory-adjustments/9999');

        $response->assertStatus(404);
    }

    public function test_view_adjustment_detail_with_relations(): void
    {
        $adjustment = $this->postJson('/api/inventory-adjustments', [
            'batch_id'             => $this->batch->id,
            'adjustment_reason_id' => $this->activeReason->id,
            'new_quantity'         => 80,
        ])->json('data.id');

        $response = $this->getJson("/api/inventory-adjustments/{$adjustment}");

        $response->assertOk()
            ->assertJsonPath('data.batch.batch_no', 'B-001')
            ->assertJsonPath('data.batch.product.sku', 'SKU-WATER')
            ->assertJsonPath('data.batch.warehouse.code', 'MAIN')
            ->assertJsonPath('data.reason.code', 'physical_count');
    }
}
