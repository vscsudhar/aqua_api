<?php

namespace Tests\Feature;

use App\Models\Dealer;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Supply;
use App\Models\SupplyItem;
use App\Models\Unit;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProductMasterApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'name' => 'Product Master Admin',
            'email' => 'productadmin@marinaaqua.com',
            'username' => 'prodadmin',
            'password' => Hash::make('Secret123!'),
            'role' => 'admin',
            'is_active' => true,
        ]);

        $this->token = $this->user->createToken('TestDevice')->plainTextToken;
    }

    public function test_category_crud_operations(): void
    {
        // 1. Create Category
        $createRes = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->postJson('/api/v1/categories', [
                'name' => 'Packaged Water',
                'code' => 'WATER',
                'description' => 'Mineral water finished goods',
            ]);

        $createRes->assertStatus(201)
            ->assertJsonPath('data.code', 'WATER');

        $categoryId = $createRes->json('data.id');

        // 2. Read Categories
        $listRes = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->getJson('/api/v1/categories?search=Water');

        $listRes->assertStatus(200)
            ->assertJsonPath('status', 'success');

        // 3. Update Category
        $updateRes = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->putJson("/api/v1/categories/{$categoryId}", [
                'name' => 'Packaged Mineral Water',
                'code' => 'WATER',
                'description' => 'Updated description',
            ]);

        $updateRes->assertStatus(200)
            ->assertJsonPath('data.name', 'Packaged Mineral Water');
    }

    public function test_unit_crud_operations(): void
    {
        // 1. Create Unit
        $createRes = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->postJson('/api/v1/units', [
                'name' => 'Litre',
                'code' => 'LTR',
                'description' => 'Volume in Litres',
            ]);

        $createRes->assertStatus(201)
            ->assertJsonPath('data.code', 'LTR');

        $unitId = $createRes->json('data.id');

        // 2. Read Units
        $listRes = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->getJson('/api/v1/units?all=true');

        $listRes->assertStatus(200)
            ->assertJsonPath('status', 'success');

        // 3. Update Unit
        $updateRes = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->putJson("/api/v1/units/{$unitId}", [
                'name' => 'Litres (Standard)',
                'code' => 'LTR',
            ]);

        $updateRes->assertStatus(200)
            ->assertJsonPath('data.name', 'Litres (Standard)');
    }

    public function test_product_crud_and_referential_integrity(): void
    {
        $category = ProductCategory::create([
            'name' => 'Water Jars',
            'code' => 'JARS',
            'is_active' => true,
        ]);

        $unit = Unit::create([
            'name' => 'Can',
            'code' => 'CAN',
            'is_active' => true,
        ]);

        // 1. Create Product
        $createRes = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->postJson('/api/v1/products', [
                'name' => '20-Litre Water Can',
                'sku' => 'MA-CAN-20L-TEST',
                'category_id' => $category->id,
                'unit_id' => $unit->id,
                'description' => 'Refill jar for distribution',
                'hsn_code' => '2201',
                'default_price' => 45.00,
                'cost_price' => 18.00,
                'tax_rate' => 18.00,
                'current_stock' => 100.00,
                'min_stock_level' => 30.00,
                'track_stock' => true,
            ]);

        $createRes->assertStatus(201)
            ->assertJsonPath('data.name', '20-Litre Water Can')
            ->assertJsonPath('data.sku', 'MA-CAN-20L-TEST')
            ->assertJsonPath('data.default_price', 45)
            ->assertJsonPath('data.cost_price', 18);

        $productId = $createRes->json('data.id');

        // 2. Update Product
        $updateRes = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->putJson("/api/v1/products/{$productId}", [
                'name' => '20-Litre Mineral Water Can (Refill)',
                'sku' => 'MA-CAN-20L-TEST',
                'category_id' => $category->id,
                'unit_id' => $unit->id,
                'default_price' => 50.00,
                'cost_price' => 20.00,
            ]);

        $updateRes->assertStatus(200)
            ->assertJsonPath('data.default_price', 50);

        // 3. Toggle Status
        $toggleRes = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->patchJson("/api/v1/products/{$productId}/toggle-status");

        $toggleRes->assertStatus(200)
            ->assertJsonPath('data.is_active', false);

        // Reactivate
        $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->patchJson("/api/v1/products/{$productId}/toggle-status")
            ->assertJsonPath('data.is_active', true);

        // 4. Test Referential Integrity:
        // When product is referenced in a supply transaction, deletion MUST NOT hard-delete, but deactivate instead!
        $dealer = Dealer::create([
            'code' => 'DLR-REF-1',
            'name' => 'Referenced Dealer',
            'phone' => '+91 99999 88888',
        ]);

        $supply = Supply::create([
            'supply_no' => 'SUP-REF-001',
            'dealer_id' => $dealer->id,
            'supply_date' => Carbon::today('Asia/Kolkata')->format('Y-m-d'),
            'total_amount' => 500.00,
            'status' => 'delivered',
        ]);

        SupplyItem::create([
            'supply_id' => $supply->id,
            'product_id' => $productId,
            'quantity' => 10,
            'unit_price' => 50.00,
            'tax_rate' => 18.00,
            'total_amount' => 500.00,
        ]);

        // Attempt delete
        $delRes = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->deleteJson("/api/v1/products/{$productId}");

        $delRes->assertStatus(200)
            ->assertSee('deactivated instead');

        // Ensure record still exists in database and is deactivated
        $product = Product::find($productId);
        $this->assertNotNull($product);
        $this->assertFalse($product->is_active);
    }

    public function test_refill_product_configuration_and_filtering(): void
    {
        $category = ProductCategory::create([
            'name' => 'Water Service',
            'code' => 'SERVICE',
            'is_active' => true,
        ]);

        $unit = Unit::create([
            'name' => 'Refill Can',
            'code' => 'REFILL',
            'is_active' => true,
        ]);

        // 1. Create Refill Product (without cost_price, current_stock, min_stock_level)
        $createRes = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->postJson('/api/v1/products', [
                'name' => '20L Can Water Refill',
                'sku' => 'REFILL-20L',
                'product_type' => 'refill',
                'category_id' => $category->id,
                'unit_id' => $unit->id,
                'default_price' => 25.00,
                'tax_rate' => 18.00,
                'hsn_code' => '2201',
                'description' => 'Water refill service only (customer jar)',
            ]);

        $createRes->assertStatus(201)
            ->assertJsonPath('data.name', '20L Can Water Refill')
            ->assertJsonPath('data.sku', 'REFILL-20L')
            ->assertJsonPath('data.product_type', 'refill')
            ->assertJsonPath('data.default_price', 25)
            ->assertJsonPath('data.cost_price', null)
            ->assertJsonPath('data.is_low_stock', false);

        $refillId = $createRes->json('data.id');

        // 2. Update Refill Product selling price
        $updateRes = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->putJson("/api/v1/products/{$refillId}", [
                'name' => '20L Premium Can Water Refill',
                'sku' => 'REFILL-20L',
                'product_type' => 'refill',
                'category_id' => $category->id,
                'unit_id' => $unit->id,
                'default_price' => 30.00,
                'tax_rate' => 18.00,
            ]);

        $updateRes->assertStatus(200)
            ->assertJsonPath('data.name', '20L Premium Can Water Refill')
            ->assertJsonPath('data.default_price', 30)
            ->assertJsonPath('data.product_type', 'refill');

        // 3. Filter by product_type=refill
        $refillFilterRes = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->getJson('/api/v1/products?product_type=refill');

        $refillFilterRes->assertStatus(200)
            ->assertJsonPath('status', 'success');
        $this->assertTrue(collect($refillFilterRes->json('data'))->contains('sku', 'REFILL-20L'));

        // 4. Filter by product_type=normal (must not contain refill product)
        $normalFilterRes = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->getJson('/api/v1/products?product_type=normal');

        $normalFilterRes->assertStatus(200);
        $this->assertFalse(collect($normalFilterRes->json('data'))->contains('sku', 'REFILL-20L'));
    }
}
