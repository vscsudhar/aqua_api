<?php

namespace Tests\Feature;

use App\Models\Dealer;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaseAndInventoryTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected ProductCategory $category;

    protected Unit $unitLitre;

    protected Unit $unitCan;

    protected Product $waterCan; // track_stock = true

    protected Product $waterBottle; // track_stock = false

    protected Supplier $supplier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]);

        $this->category = ProductCategory::create([
            'name' => 'Packaged Water',
            'code' => 'PKG-WTR',
            'is_active' => true,
        ]);

        $this->unitLitre = Unit::create([
            'name' => 'Litre',
            'code' => 'LTR',
            'is_active' => true,
        ]);

        $this->unitCan = Unit::create([
            'name' => '20L Can',
            'code' => 'CAN-20L',
            'is_active' => true,
        ]);

        // Stock-tracked product
        $this->waterCan = Product::create([
            'category_id' => $this->category->id,
            'unit_id' => $this->unitCan->id,
            'name' => '20 Litre Water Can',
            'sku' => 'WTR-CAN-20L',
            'unit' => 'CAN',
            'default_price' => 45.00,
            'cost_price' => 20.00,
            'tax_rate' => 12.00,
            'current_stock' => 100.00,
            'min_stock_level' => 30.00,
            'track_stock' => true,
            'is_active' => true,
        ]);

        // Non-stock-tracked product
        $this->waterBottle = Product::create([
            'category_id' => $this->category->id,
            'unit_id' => $this->unitLitre->id,
            'name' => '1 Litre Mineral Bottle',
            'sku' => 'WTR-BTL-1L',
            'unit' => 'BTL',
            'default_price' => 20.00,
            'cost_price' => 10.00,
            'tax_rate' => 18.00,
            'current_stock' => 0.00,
            'min_stock_level' => 10.00,
            'track_stock' => false,
            'is_active' => true,
        ]);

        $this->supplier = Supplier::create([
            'code' => 'SUP-001',
            'name' => 'Aqua Pack Suppliers Ltd',
            'contact_person' => 'Rajesh Sharma',
            'phone' => '9876543210',
            'email' => 'rajesh@aquapack.com',
            'opening_balance' => 5000.00,
            'current_balance' => 5000.00,
            'is_active' => true,
        ]);
    }

    public function test_can_create_and_list_suppliers(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/v1/suppliers', [
            'code' => 'SUP-002',
            'name' => 'Blue Ocean Caps & Preforms',
            'phone' => '9845012345',
            'contact_person' => 'Anand Kumar',
            'opening_balance' => 1500.00,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.code', 'SUP-002')
            ->assertJsonPath('data.name', 'Blue Ocean Caps & Preforms');

        $list = $this->actingAs($this->user)->getJson('/api/v1/suppliers');
        $list->assertStatus(200)
            ->assertJsonPath('pagination.total', 2);
    }

    public function test_posting_purchase_increases_stock_for_tracked_products_and_creates_movements(): void
    {
        $initialStock = (float) $this->waterCan->current_stock;

        $response = $this->actingAs($this->user)->postJson('/api/v1/purchases', [
            'supplier_id' => $this->supplier->id,
            'invoice_no' => 'INV-2026-889',
            'purchase_date' => now()->toDateString(),
            'discount_amount' => 100.00,
            'items' => [
                [
                    'product_id' => $this->waterCan->id,
                    'quantity' => 50,
                    'unit_cost' => 22.50,
                    'tax_rate' => 12.00,
                ],
            ],
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.supplier_name', 'Aqua Pack Suppliers Ltd');

        // Check product stock increased
        $this->waterCan->refresh();
        $this->assertEquals($initialStock + 50, (float) $this->waterCan->current_stock);

        // Check InventoryMovement recorded
        $movement = InventoryMovement::where('product_id', $this->waterCan->id)
            ->where('movement_type', 'purchase_in')
            ->first();

        $this->assertNotNull($movement);
        $this->assertEquals(50.00, (float) $movement->quantity);
        $this->assertEquals(50.00, (float) $movement->signed_quantity);
        $this->assertEquals($initialStock, (float) $movement->balance_before);
        $this->assertEquals($initialStock + 50, (float) $movement->balance_after);
    }

    public function test_posting_purchase_for_untracked_products_does_not_modify_stock(): void
    {
        $initialStock = (float) $this->waterBottle->current_stock;

        $response = $this->actingAs($this->user)->postJson('/api/v1/purchases', [
            'supplier_id' => $this->supplier->id,
            'invoice_no' => 'INV-999',
            'purchase_date' => now()->toDateString(),
            'items' => [
                [
                    'product_id' => $this->waterBottle->id,
                    'quantity' => 200,
                    'unit_cost' => 8.00,
                ],
            ],
        ]);

        $response->assertStatus(201);

        $this->waterBottle->refresh();
        $this->assertEquals($initialStock, (float) $this->waterBottle->current_stock);

        // No inventory movement for untracked product
        $movementExists = InventoryMovement::where('product_id', $this->waterBottle->id)->exists();
        $this->assertFalse($movementExists);
    }

    public function test_purchase_cost_is_separate_from_dealer_selling_price(): void
    {
        $sellingPrice = (float) $this->waterCan->default_price;

        $this->actingAs($this->user)->postJson('/api/v1/purchases', [
            'supplier_id' => $this->supplier->id,
            'invoice_no' => 'INV-COST-CHECK',
            'purchase_date' => now()->toDateString(),
            'items' => [
                [
                    'product_id' => $this->waterCan->id,
                    'quantity' => 20,
                    'unit_cost' => 19.50, // lower purchase cost
                ],
            ],
        ]);

        $this->waterCan->refresh();
        // Selling price must be preserved exactly
        $this->assertEquals($sellingPrice, (float) $this->waterCan->default_price);
        // Cost price snapshot is updated
        $this->assertEquals(19.50, (float) $this->waterCan->cost_price);
    }

    public function test_editing_posted_purchase_adjusts_inventory_and_line_items(): void
    {
        // 1. Post initial purchase with 40 cans
        $postRes = $this->actingAs($this->user)->postJson('/api/v1/purchases', [
            'supplier_id' => $this->supplier->id,
            'invoice_no' => 'INV-EDIT-1',
            'purchase_date' => now()->toDateString(),
            'items' => [
                [
                    'product_id' => $this->waterCan->id,
                    'quantity' => 40,
                    'unit_cost' => 21.00,
                ],
            ],
        ]);

        $purchaseId = $postRes->json('data.id');
        $this->waterCan->refresh();
        $this->assertEquals(140.00, (float) $this->waterCan->current_stock);

        // 2. Edit purchase to 60 cans
        $editRes = $this->actingAs($this->user)->putJson("/api/v1/purchases/{$purchaseId}", [
            'supplier_id' => $this->supplier->id,
            'invoice_no' => 'INV-EDIT-1-UPDATED',
            'purchase_date' => now()->toDateString(),
            'items' => [
                [
                    'product_id' => $this->waterCan->id,
                    'quantity' => 60,
                    'unit_cost' => 21.00,
                ],
            ],
        ]);

        $editRes->assertStatus(200);

        $this->waterCan->refresh();
        // 100 base + 60 edited = 160
        $this->assertEquals(160.00, (float) $this->waterCan->current_stock);
    }

    public function test_reversing_posted_purchase_deducts_stock_and_records_reversal_movements(): void
    {
        $postRes = $this->actingAs($this->user)->postJson('/api/v1/purchases', [
            'supplier_id' => $this->supplier->id,
            'invoice_no' => 'INV-REVERSE-1',
            'purchase_date' => now()->toDateString(),
            'items' => [
                [
                    'product_id' => $this->waterCan->id,
                    'quantity' => 25,
                    'unit_cost' => 22.00,
                ],
            ],
        ]);

        $purchaseId = $postRes->json('data.id');
        $this->waterCan->refresh();
        $this->assertEquals(125.00, (float) $this->waterCan->current_stock);

        // Reverse purchase
        $revRes = $this->actingAs($this->user)->postJson("/api/v1/purchases/{$purchaseId}/reverse", [
            'reason' => 'Vendor sent damaged shipment',
        ]);

        $revRes->assertStatus(200)
            ->assertJsonPath('data.status', 'cancelled');

        $this->waterCan->refresh();
        $this->assertEquals(100.00, (float) $this->waterCan->current_stock);

        $reversalMov = InventoryMovement::where('product_id', $this->waterCan->id)
            ->where('movement_type', 'purchase_reversal')
            ->first();

        $this->assertNotNull($reversalMov);
        $this->assertEquals(-25.00, (float) $reversalMov->signed_quantity);
    }

    public function test_dealer_supply_reduces_stock_only_for_tracked_products(): void
    {
        $dealer = Dealer::create([
            'code' => 'DLR-STOCK-TEST',
            'name' => 'Royal Fresh Supermarket',
            'phone' => '9888877777',
            'is_active' => true,
        ]);

        $canBefore = (float) $this->waterCan->current_stock;
        $bottleBefore = (float) $this->waterBottle->current_stock;

        $response = $this->actingAs($this->user)->postJson('/api/v1/supplies', [
            'dealer_id' => $dealer->id,
            'supply_date' => now()->toDateString(),
            'items' => [
                [
                    'product_id' => $this->waterCan->id, // tracked
                    'quantity' => 15,
                    'custom_price' => 50.00,
                ],
                [
                    'product_id' => $this->waterBottle->id, // untracked
                    'quantity' => 30,
                    'custom_price' => 18.00,
                ],
            ],
        ]);

        $response->assertStatus(201);

        $this->waterCan->refresh();
        $this->waterBottle->refresh();

        // Tracked can reduced by 15
        $this->assertEquals($canBefore - 15, (float) $this->waterCan->current_stock);

        // Untracked bottle unchanged
        $this->assertEquals($bottleBefore, (float) $this->waterBottle->current_stock);

        // Movement created for tracked product only
        $supplyMov = InventoryMovement::where('product_id', $this->waterCan->id)
            ->where('movement_type', 'supply_out')
            ->first();
        $this->assertNotNull($supplyMov);
        $this->assertEquals(-15.00, (float) $supplyMov->signed_quantity);

        $bottleMov = InventoryMovement::where('product_id', $this->waterBottle->id)
            ->where('movement_type', 'supply_out')
            ->first();
        $this->assertNull($bottleMov);
    }

    public function test_authorized_stock_adjustment_updates_stock_and_records_movement_with_reason(): void
    {
        $before = (float) $this->waterCan->current_stock;

        $response = $this->actingAs($this->user)->postJson('/api/v1/inventory/adjustments', [
            'product_id' => $this->waterCan->id,
            'adjustment_type' => 'damage',
            'quantity' => 4,
            'reason' => 'Leakage during warehouse handling',
            'authorized_by' => 'Operations Head - Ramesh',
            'notes' => 'Inspected and written off',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.adjustment_type', 'damage')
            ->assertJsonPath('data.authorized_by', 'Operations Head - Ramesh');
        $this->assertEquals($before - 4, (float) $response->json('data.stock_after'));

        $this->waterCan->refresh();
        $this->assertEquals($before - 4, (float) $this->waterCan->current_stock);

        $adjMov = InventoryMovement::where('product_id', $this->waterCan->id)
            ->where('movement_type', 'stock_adjustment')
            ->first();
        $this->assertNotNull($adjMov);
        $this->assertEquals(-4.00, (float) $adjMov->signed_quantity);
    }

    public function test_inventory_summary_metrics_and_low_stock_alerts(): void
    {
        // Set stock to 25 which is below min_stock_level of 30
        $this->waterCan->current_stock = 25.00;
        $this->waterCan->save();

        $response = $this->actingAs($this->user)->getJson('/api/v1/inventory/summary');

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.total_tracked_products', 1)
            ->assertJsonPath('data.current_total_stock', 25)
            ->assertJsonPath('data.low_stock_count', 1);

        $this->assertEquals('20 Litre Water Can', $response->json('data.low_stock_items.0.name'));
    }

    public function test_cannot_delete_supplier_with_purchases(): void
    {
        Purchase::create([
            'purchase_no' => 'PUR-REF-101',
            'supplier_id' => $this->supplier->id,
            'supplier_name' => $this->supplier->name,
            'purchase_date' => now()->toDateString(),
            'total_amount' => 500.00,
            'status' => 'received',
        ]);

        $response = $this->actingAs($this->user)->deleteJson("/api/v1/suppliers/{$this->supplier->id}");

        $response->assertStatus(422)
            ->assertJsonPath('status', 'error');

        $this->assertDatabaseHas('suppliers', [
            'id' => $this->supplier->id,
            'deleted_at' => null,
        ]);
    }
}
