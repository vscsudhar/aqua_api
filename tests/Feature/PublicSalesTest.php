<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\PublicParty;
use App\Models\PublicSale;
use App\Models\PublicSaleItem;
use App\Models\Unit;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PublicSalesTest extends TestCase
{
    use RefreshDatabase;

    protected Company $companyA;
    protected Company $companyB;
    protected User $userA;
    protected User $userB;
    protected ProductCategory $category;
    protected Unit $unitBottle;
    protected Unit $unitCan;
    protected Product $productWithoutInvoice;
    protected Product $productForInvoice;
    protected Product $productRefill;
    protected Product $productTracked;

    protected function setUp(): void
    {
        parent::setUp();

        $this->companyA = Company::create([
            'name' => 'Marina Aqua Company A',
            'company_code' => 'COMP-A',
            'status' => 'active',
        ]);

        $this->companyB = Company::create([
            'name' => 'Other Company B',
            'company_code' => 'COMP-B',
            'status' => 'active',
        ]);

        $this->userA = User::factory()->create([
            'company_id' => $this->companyA->id,
            'role' => 'admin',
            'is_active' => true,
        ]);

        $this->userB = User::factory()->create([
            'company_id' => $this->companyB->id,
            'role' => 'admin',
            'is_active' => true,
        ]);

        $this->category = ProductCategory::create([
            'company_id' => $this->companyA->id,
            'name' => 'Beverages',
            'code' => 'BEV',
            'is_active' => true,
        ]);

        $this->unitBottle = Unit::create([
            'company_id' => $this->companyA->id,
            'name' => 'Bottle',
            'code' => 'BTL',
            'is_active' => true,
        ]);

        $this->unitCan = Unit::create([
            'company_id' => $this->companyA->id,
            'name' => '20L Can',
            'code' => 'CAN',
            'is_active' => true,
        ]);

        // 1. Without for Invoice (eligible)
        $this->productWithoutInvoice = Product::create([
            'company_id' => $this->companyA->id,
            'category_id' => $this->category->id,
            'unit_id' => $this->unitBottle->id,
            'name' => '1 Litre Water Bottle',
            'sku' => '1L-BTL',
            'product_type' => 'normal',
            'unit' => 'Bottle',
            'default_price' => 20.00,
            'cost_price' => 12.00,
            'tax_rate' => 18.00,
            'current_stock' => 0.00,
            'track_stock' => false, // Case A: tracking not started
            'is_active' => true,
            'for_invoice' => false,
        ]);

        // 2. For Invoice (excluded)
        $this->productForInvoice = Product::create([
            'company_id' => $this->companyA->id,
            'category_id' => $this->category->id,
            'unit_id' => $this->unitBottle->id,
            'name' => 'Special GST Mineral 500ml',
            'sku' => '500ML-GST',
            'product_type' => 'normal',
            'unit' => 'Bottle',
            'default_price' => 15.00,
            'cost_price' => 8.00,
            'tax_rate' => 18.00,
            'current_stock' => 100.00,
            'track_stock' => true,
            'is_active' => true,
            'for_invoice' => true, // Excluded from public sales
        ]);

        // 3. Refill Product (eligible where for_invoice = false)
        $this->productRefill = Product::create([
            'company_id' => $this->companyA->id,
            'category_id' => $this->category->id,
            'unit_id' => $this->unitCan->id,
            'name' => '20 Litre Water Refill',
            'sku' => '20L-REFILL',
            'product_type' => 'refill',
            'unit' => '20L Can',
            'default_price' => 35.00,
            'cost_price' => 10.00,
            'tax_rate' => 0.00,
            'current_stock' => 0.00,
            'track_stock' => false,
            'is_active' => true,
            'for_invoice' => false,
        ]);

        // 4. Stock-tracked product with inventory (Cases B & C)
        $this->productTracked = Product::create([
            'company_id' => $this->companyA->id,
            'category_id' => $this->category->id,
            'unit_id' => $this->unitBottle->id,
            'name' => '2 Litre Water Bottle',
            'sku' => '2L-BTL',
            'product_type' => 'normal',
            'unit' => 'Bottle',
            'default_price' => 30.00,
            'cost_price' => 18.00,
            'tax_rate' => 18.00,
            'current_stock' => 50.00,
            'track_stock' => true,
            'inventory_start_date' => Carbon::now()->subDays(10)->toDateString(),
            'is_active' => true,
            'for_invoice' => false,
        ]);
    }

    /**
     * Test 1: Party Management - Create, Edit, List, and Delete without transactions.
     */
    public function test_party_management_lifecycle(): void
    {
        Sanctum::actingAs($this->userA);

        // 1. Create party
        $response = $this->postJson('/api/v1/public-sales/parties', [
            'party_name' => 'Ramesh Traders',
            'contact_number' => '9876543210',
            'address' => '12 Market Street, Chennai',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.party_name', 'Ramesh Traders');

        $partyId = $response->json('data.id');

        // 2. List parties
        $listRes = $this->getJson('/api/v1/public-sales/parties');
        $listRes->assertStatus(200)
            ->assertJsonFragment(['party_name' => 'Ramesh Traders']);

        // 3. Edit party
        $updateRes = $this->putJson("/api/v1/public-sales/parties/{$partyId}", [
            'party_name' => 'Ramesh Mega Traders',
            'contact_number' => '9876543211',
        ]);
        $updateRes->assertStatus(200)
            ->assertJsonPath('data.party_name', 'Ramesh Mega Traders');

        // 4. Delete party (no transactions yet)
        $delRes = $this->deleteJson("/api/v1/public-sales/parties/{$partyId}");
        $delRes->assertStatus(200);

        $this->assertDatabaseMissing('public_parties', ['id' => $partyId]);
    }

    /**
     * Test 2: Product Catalog Filtering - Only Without for Invoice and Refills.
     */
    public function test_product_catalog_filter_rules(): void
    {
        Sanctum::actingAs($this->userA);

        $response = $this->getJson('/api/v1/public-sales/products');
        $response->assertStatus(200);

        $productNames = collect($response->json('data'))->pluck('name')->toArray();

        // Must include Without for Invoice
        $this->assertContains('1 Litre Water Bottle', $productNames);
        // Must include Refills
        $this->assertContains('20 Litre Water Refill', $productNames);
        $this->assertContains('2 Litre Water Bottle', $productNames);
        // Must strictly EXCLUDE For Invoice products
        $this->assertNotContains('Special GST Mineral 500ml', $productNames);
    }

    /**
     * Test 3: Public Sales Entry Creation - Case A (Tracking not started).
     */
    public function test_create_sale_case_a_tracking_not_started(): void
    {
        Sanctum::actingAs($this->userA);

        $party = PublicParty::create([
            'company_id' => $this->companyA->id,
            'party_name' => 'Hotel Saravana',
        ]);

        // Sale with track_stock = false (1L bottle) and refill
        $res = $this->postJson('/api/v1/public-sales', [
            'party_id' => $party->id,
            'sale_date' => Carbon::today('Asia/Kolkata')->toDateString(),
            'cash_amount' => 200.00,
            'upi_amount' => 150.00,
            'items' => [
                [
                    'product_id' => $this->productWithoutInvoice->id,
                    'rate' => 20.00,
                    'quantity' => 10,
                    'amount' => 200.00,
                ],
                [
                    'product_id' => $this->productRefill->id,
                    'rate' => 35.00,
                    'quantity' => 5,
                    'amount' => 150.00, // Manual discount amount
                ],
            ],
        ]);

        $res->assertStatus(201)
            ->assertJsonPath('data.total_amount', '350.00')
            ->assertJsonPath('data.cash_amount', '200.00')
            ->assertJsonPath('data.upi_amount', '150.00');

        // Verify stock is NOT deducted because track_stock is false
        $this->assertEquals(0, InventoryMovement::where('reference_type', 'public_sale')->count());
        $this->productWithoutInvoice->refresh();
        $this->assertEquals(0.00, (float) $this->productWithoutInvoice->current_stock);
    }

    /**
     * Test 4: Public Sales Entry Creation - Case B (Sufficient Stock Deducted).
     */
    public function test_create_sale_case_b_stock_deducted(): void
    {
        Sanctum::actingAs($this->userA);

        $party = PublicParty::create([
            'company_id' => $this->companyA->id,
            'party_name' => 'Direct Walk-in Party',
        ]);

        $initialStock = (float) $this->productTracked->current_stock; // 50

        $res = $this->postJson('/api/v1/public-sales', [
            'party_id' => $party->id,
            'sale_date' => Carbon::today('Asia/Kolkata')->toDateString(),
            'cash_amount' => 300.00,
            'upi_amount' => 0.00,
            'items' => [
                [
                    'product_id' => $this->productTracked->id,
                    'rate' => 30.00,
                    'quantity' => 10,
                    'amount' => 300.00,
                ],
            ],
        ]);

        $res->assertStatus(201);

        $this->productTracked->refresh();
        // 50 - 10 = 40
        $this->assertEquals(40.00, (float) $this->productTracked->current_stock);

        // Verify inventory movement record
        $mov = InventoryMovement::where('reference_type', 'public_sale')->first();
        $this->assertNotNull($mov);
        $this->assertEquals('public_sale_out', $mov->movement_type);
        $this->assertEquals(10.00, (float) $mov->quantity);
        $this->assertEquals(-10.00, (float) $mov->signed_quantity);
    }

    /**
     * Test 5: Case C - Insufficient Stock Confirmation Flow.
     */
    public function test_create_sale_case_c_insufficient_stock_prompt_and_confirmation(): void
    {
        Sanctum::actingAs($this->userA);

        $party = PublicParty::create([
            'company_id' => $this->companyA->id,
            'party_name' => 'Big Event Party',
        ]);

        // Current stock is 50. Request 80.
        // Step 1: Without confirmation flag -> returns 409 with exact message
        $res1 = $this->postJson('/api/v1/public-sales', [
            'party_id' => $party->id,
            'sale_date' => Carbon::today('Asia/Kolkata')->toDateString(),
            'items' => [
                [
                    'product_id' => $this->productTracked->id,
                    'rate' => 30.00,
                    'quantity' => 80,
                    'amount' => 2400.00,
                ],
            ],
        ]);

        $res1->assertStatus(409)
            ->assertJsonPath('is_stock_insufficient', true)
            ->assertJsonFragment(['Stock Not Available. Stock illa. Public Sales-la add pannalama?']);

        // Step 2: With confirm_insufficient_stock = true
        // Must deduct ONLY available stock (50), leave 0, without creating negative inventory
        $res2 = $this->postJson('/api/v1/public-sales', [
            'party_id' => $party->id,
            'sale_date' => Carbon::today('Asia/Kolkata')->toDateString(),
            'cash_amount' => 2400.00,
            'confirm_insufficient_stock' => true,
            'items' => [
                [
                    'product_id' => $this->productTracked->id,
                    'rate' => 30.00,
                    'quantity' => 80,
                    'amount' => 2400.00,
                ],
            ],
        ]);

        $res2->assertStatus(201);

        $this->productTracked->refresh();
        $this->assertEquals(0.00, (float) $this->productTracked->current_stock);

        $item = PublicSaleItem::where('product_id', $this->productTracked->id)->latest('id')->first();
        $this->assertEquals(50.00, (float) $item->stock_deducted);
    }

    /**
     * Test 6: Safe Deletion & Stock Reversal.
     */
    public function test_delete_sale_reverses_stock_movements(): void
    {
        Sanctum::actingAs($this->userA);

        $party = PublicParty::create([
            'company_id' => $this->companyA->id,
            'party_name' => 'Reversal Test Party',
        ]);

        $this->productTracked->update(['current_stock' => 50.00]);

        $res = $this->postJson('/api/v1/public-sales', [
            'party_id' => $party->id,
            'sale_date' => Carbon::today('Asia/Kolkata')->toDateString(),
            'cash_amount' => 300.00,
            'items' => [
                [
                    'product_id' => $this->productTracked->id,
                    'rate' => 30.00,
                    'quantity' => 15,
                    'amount' => 450.00,
                ],
            ],
        ]);

        $saleId = $res->json('data.id');
        $this->productTracked->refresh();
        $this->assertEquals(35.00, (float) $this->productTracked->current_stock);

        // Delete the sale
        $delRes = $this->deleteJson("/api/v1/public-sales/{$saleId}");
        $delRes->assertStatus(200);

        // Stock must be restored to 50
        $this->productTracked->refresh();
        $this->assertEquals(50.00, (float) $this->productTracked->current_stock);
    }

    /**
     * Test 7: Party Deletion Protection when transactions exist.
     */
    public function test_cannot_delete_party_with_transactions(): void
    {
        Sanctum::actingAs($this->userA);

        $party = PublicParty::create([
            'company_id' => $this->companyA->id,
            'party_name' => 'Protected Party',
        ]);

        PublicSale::create([
            'company_id' => $this->companyA->id,
            'party_id' => $party->id,
            'sale_date' => Carbon::today('Asia/Kolkata')->toDateString(),
            'total_amount' => 100.00,
        ]);

        $res = $this->deleteJson("/api/v1/public-sales/parties/{$party->id}");
        $res->assertStatus(422)
            ->assertJsonFragment(['Cannot delete party with existing transaction history. Sales and statements must be preserved.']);
    }

    /**
     * Test 8: Multiple Entries on Same Day with Distinct Timestamps.
     */
    public function test_multiple_entries_on_same_day(): void
    {
        Sanctum::actingAs($this->userA);

        $party = PublicParty::create([
            'company_id' => $this->companyA->id,
            'party_name' => 'Daily Repeat Party',
        ]);

        $today = Carbon::today('Asia/Kolkata')->toDateString();

        // Entry 1
        $res1 = $this->postJson('/api/v1/public-sales', [
            'party_id' => $party->id,
            'sale_date' => $today,
            'cash_amount' => 250.00,
            'items' => [
                ['product_id' => $this->productWithoutInvoice->id, 'rate' => 25.00, 'quantity' => 10, 'amount' => 250.00],
            ],
        ]);
        $res1->assertStatus(201);

        // Entry 2 on same day
        $res2 = $this->postJson('/api/v1/public-sales', [
            'party_id' => $party->id,
            'sale_date' => $today,
            'upi_amount' => 450.00,
            'items' => [
                ['product_id' => $this->productWithoutInvoice->id, 'rate' => 25.00, 'quantity' => 18, 'amount' => 450.00],
            ],
        ]);
        $res2->assertStatus(201);

        $this->assertNotEquals($res1->json('data.id'), $res2->json('data.id'));
        $this->assertEquals(2, PublicSale::where('party_id', $party->id)->whereDate('sale_date', $today)->count());
    }

    /**
     * Test 9: Monthly Statement and Daily Details APIs.
     */
    public function test_party_monthly_statement_and_daily_details(): void
    {
        Sanctum::actingAs($this->userA);

        $party = PublicParty::create([
            'company_id' => $this->companyA->id,
            'party_name' => 'Statement Test Party',
        ]);

        $today = Carbon::today('Asia/Kolkata');
        $saleDate = $today->toDateString();

        $this->postJson('/api/v1/public-sales', [
            'party_id' => $party->id,
            'sale_date' => $saleDate,
            'cash_amount' => 500.00,
            'upi_amount' => 200.00,
            'items' => [
                ['product_id' => $this->productWithoutInvoice->id, 'rate' => 20.00, 'quantity' => 25, 'amount' => 500.00],
                ['product_id' => $this->productRefill->id, 'rate' => 40.00, 'quantity' => 5, 'amount' => 200.00],
            ],
        ]);

        // Monthly statement
        $stmtRes = $this->getJson("/api/v1/public-sales/parties/{$party->id}/statement?year={$today->year}&month={$today->month}");
        $stmtRes->assertStatus(200)
            ->assertJsonPath('data.party.party_name', 'Statement Test Party')
            ->assertJsonPath('data.monthly_totals.total_cash', 500)
            ->assertJsonPath('data.monthly_totals.total_upi', 200)
            ->assertJsonPath('data.monthly_totals.grand_total', 700);

        // Daily details
        $dailyRes = $this->getJson("/api/v1/public-sales/parties/{$party->id}/daily-details?date={$saleDate}");
        $dailyRes->assertStatus(200)
            ->assertJsonPath('data.day_summary.total_entries', 1)
            ->assertJsonPath('data.day_summary.daily_grand_total', 700);
    }

    /**
     * Test 10: Multi-tenant Company Data Isolation.
     */
    public function test_company_isolation(): void
    {
        // User A creates party
        Sanctum::actingAs($this->userA);
        $partyA = PublicParty::create([
            'company_id' => $this->companyA->id,
            'party_name' => 'Company A Party',
        ]);

        // User B cannot access Party A
        Sanctum::actingAs($this->userB);
        $res = $this->getJson("/api/v1/public-sales/parties/{$partyA->id}/statement");
        $res->assertStatus(404);
    }
}
