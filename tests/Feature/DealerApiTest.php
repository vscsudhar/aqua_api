<?php

namespace Tests\Feature;

use App\Models\Dealer;
use App\Models\DealerLedgerEntry;
use App\Models\DealerProductPrice;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Supply;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DealerApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected ProductCategory $category;

    protected Product $product20L;

    protected Product $product1L;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]);

        $this->category = ProductCategory::create([
            'name' => 'Packaged Water',
            'code' => 'WATER',
            'is_active' => true,
        ]);

        $this->product20L = Product::create([
            'category_id' => $this->category->id,
            'name' => '20-Litre Water Can',
            'sku' => 'CAN-20L',
            'unit' => '20L Can',
            'default_price' => 45.00,
            'cost_price' => 18.00,
            'tax_rate' => 18.00,
            'current_stock' => 500,
            'min_stock_level' => 100,
            'is_active' => true,
        ]);

        $this->product1L = Product::create([
            'category_id' => $this->category->id,
            'name' => '1L Bottled Water (Box of 12)',
            'sku' => 'BOT-1L-BOX',
            'unit' => 'Box',
            'default_price' => 180.00,
            'cost_price' => 95.00,
            'tax_rate' => 18.00,
            'current_stock' => 200,
            'min_stock_level' => 50,
            'is_active' => true,
        ]);
    }

    public function test_can_list_dealers_with_search_and_pagination(): void
    {
        Dealer::create([
            'code' => 'DLR-CHENNAI-01',
            'name' => 'Murugan Water Agency',
            'phone' => '+91 94441 11111',
            'opening_balance' => 5000,
            'is_active' => true,
        ]);

        Dealer::create([
            'code' => 'DLR-TAMBARAM-02',
            'name' => 'Annai Cool Drinks',
            'phone' => '+91 98882 22222',
            'opening_balance' => 2000,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/dealers?search=Murugan');

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.code', 'DLR-CHENNAI-01')
            ->assertJsonPath('data.0.name', 'Murugan Water Agency');
    }

    public function test_can_create_dealer_with_opening_balance_and_ledger_entry_created(): void
    {
        $payload = [
            'name' => 'Sri Balaji Aqua Distributors',
            'code' => 'DLR-BALAJI-01',
            'contact_person' => 'R. Balaji',
            'phone' => '+91 97773 33333',
            'email' => 'balaji.aqua@gmail.com',
            'address' => 'No. 45, Velachery Main Road, Chennai',
            'opening_balance' => 15000.00,
            'opening_date' => '2026-10-01',
            'notes' => 'New exclusive distributor for South Chennai',
            'is_active' => true,
        ];

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/dealers', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.code', 'DLR-BALAJI-01')
            ->assertJsonPath('data.opening_balance', 15000);

        $this->assertDatabaseHas('dealers', [
            'code' => 'DLR-BALAJI-01',
            'name' => 'Sri Balaji Aqua Distributors',
        ]);

        // Assert single opening balance ledger entry was created
        $dealer = Dealer::where('code', 'DLR-BALAJI-01')->first();
        $this->assertNotNull($dealer);

        $entry = DealerLedgerEntry::where('dealer_id', $dealer->id)->first();
        $this->assertNotNull($entry);
        $this->assertEquals('opening_balance', $entry->entry_type);
        $this->assertEquals('OPB-DLR-BALAJI-01', $entry->reference_no);
        $this->assertEquals(15000.00, (float) $entry->debit);
        $this->assertEquals(0.00, (float) $entry->credit);
        $this->assertEquals('2026-10-01', $entry->entry_date->toDateString());

        $this->assertEquals(1, DealerLedgerEntry::where('dealer_id', $dealer->id)->count());
    }

    public function test_opening_balance_is_never_duplicated_on_multiple_updates(): void
    {
        $dealer = Dealer::create([
            'code' => 'DLR-UNIQUE-01',
            'name' => 'Ganesh Enterprises',
            'phone' => '+91 91111 00000',
            'opening_balance' => 8000.00,
            'opening_date' => '2026-10-15',
            'is_active' => true,
        ]);

        $dealer->syncOpeningBalanceLedger($this->admin->id);
        $this->assertEquals(1, DealerLedgerEntry::where('dealer_id', $dealer->id)->count());

        // Update dealer with updated opening balance
        $updatePayload = [
            'name' => 'Ganesh Enterprises Updated',
            'code' => 'DLR-UNIQUE-01',
            'phone' => '+91 91111 00000',
            'opening_balance' => 10000.00,
            'opening_date' => '2026-10-15',
            'is_active' => true,
        ];

        $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/v1/dealers/{$dealer->id}", $updatePayload)
            ->assertStatus(200);

        // Verify count remains strictly 1 and updated value is reflected
        $this->assertEquals(1, DealerLedgerEntry::where('dealer_id', $dealer->id)->count());
        $entry = DealerLedgerEntry::where('dealer_id', $dealer->id)->first();
        $this->assertEquals(10000.00, (float) $entry->debit);
    }

    public function test_validates_unique_dealer_code(): void
    {
        Dealer::create([
            'code' => 'DLR-DUPLICATE',
            'name' => 'Existing Dealer',
            'phone' => '+91 99999 88888',
        ]);

        $payload = [
            'name' => 'Second Dealer with same code',
            'code' => 'DLR-DUPLICATE',
            'phone' => '+91 98888 77777',
        ];

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/dealers', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['code']);
    }

    public function test_cannot_delete_dealer_with_recorded_transactions_deactivates_instead(): void
    {
        $dealer = Dealer::create([
            'code' => 'DLR-PROTECTED',
            'name' => 'Protected Dealer with Supplies',
            'phone' => '+91 90000 11111',
            'is_active' => true,
        ]);

        // Create a supply delivery to reference this dealer
        Supply::create([
            'supply_no' => 'SUP-TEST-001',
            'dealer_id' => $dealer->id,
            'supply_date' => '2026-10-28',
            'total_amount' => 450.00,
            'status' => 'delivered',
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->deleteJson("/api/v1/dealers/{$dealer->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.is_active', false);

        // Dealer must still exist in DB (not hard-deleted) with is_active = false
        $this->assertDatabaseHas('dealers', [
            'id' => $dealer->id,
            'is_active' => false,
        ]);
    }

    public function test_can_toggle_dealer_active_status(): void
    {
        $dealer = Dealer::create([
            'code' => 'DLR-TOGGLE',
            'name' => 'Toggle Status Dealer',
            'phone' => '+91 92222 33333',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->patchJson("/api/v1/dealers/{$dealer->id}/toggle-status");

        $response->assertStatus(200)
            ->assertJsonPath('data.is_active', false);

        $this->assertFalse($dealer->fresh()->is_active);
    }

    public function test_different_dealers_can_have_different_prices_for_the_same_product(): void
    {
        // Dealer A: Platinum tier dealer (gets 20L can @ 38.00)
        $dealerA = Dealer::create([
            'code' => 'DLR-PLATINUM',
            'name' => 'Platinum Dealer A',
            'phone' => '+91 93333 44444',
            'is_active' => true,
        ]);

        // Dealer B: Gold tier dealer (gets 20L can @ 40.00)
        $dealerB = Dealer::create([
            'code' => 'DLR-GOLD',
            'name' => 'Gold Dealer B',
            'phone' => '+91 94444 55555',
            'is_active' => true,
        ]);

        // Dealer C: Regular dealer (has no custom pricing, gets catalog price 45.00)
        $dealerC = Dealer::create([
            'code' => 'DLR-REGULAR',
            'name' => 'Regular Dealer C',
            'phone' => '+91 95555 66666',
            'is_active' => true,
        ]);

        // Set Custom Price for Dealer A: 38.00
        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/dealers/{$dealerA->id}/prices", [
                'product_id' => $this->product20L->id,
                'custom_price' => 38.00,
                'effective_from' => '2026-01-01',
                'notes' => 'Contract rate 38/can',
            ])->assertStatus(201)
            ->assertJsonPath('data.custom_price', 38);

        // Set Custom Price for Dealer B: 40.00
        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/dealers/{$dealerB->id}/prices", [
                'product_id' => $this->product20L->id,
                'custom_price' => 40.00,
                'effective_from' => '2026-01-01',
                'notes' => 'Tier 2 rate 40/can',
            ])->assertStatus(201)
            ->assertJsonPath('data.custom_price', 40);

        // Verify Dealer A resolves to 38.00
        $resA = $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/v1/dealers/{$dealerA->id}/prices/resolve?product_id={$this->product20L->id}");
        $resA->assertStatus(200)
            ->assertJsonPath('data.price', 38)
            ->assertJsonPath('data.is_custom', true);

        // Verify Dealer B resolves to 40.00
        $resB = $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/v1/dealers/{$dealerB->id}/prices/resolve?product_id={$this->product20L->id}");
        $resB->assertStatus(200)
            ->assertJsonPath('data.price', 40)
            ->assertJsonPath('data.is_custom', true);

        // Verify Dealer C resolves to standard catalog price 45.00
        $resC = $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/v1/dealers/{$dealerC->id}/prices/resolve?product_id={$this->product20L->id}");
        $resC->assertStatus(200)
            ->assertJsonPath('data.price', 45)
            ->assertJsonPath('data.is_custom', false);
    }

    public function test_preserves_price_history_using_effective_from_and_effective_to(): void
    {
        $dealer = Dealer::create([
            'code' => 'DLR-HISTORY',
            'name' => 'History Test Dealer',
            'phone' => '+91 96666 77777',
            'is_active' => true,
        ]);

        // Phase 1: Set price 36.00 effective from 2026-01-01
        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/dealers/{$dealer->id}/prices", [
                'product_id' => $this->product20L->id,
                'custom_price' => 36.00,
                'effective_from' => '2026-01-01',
            ])->assertStatus(201);

        // Phase 2: From 2026-07-01, rate is revised to 39.00
        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/dealers/{$dealer->id}/prices", [
                'product_id' => $this->product20L->id,
                'custom_price' => 39.00,
                'effective_from' => '2026-07-01',
            ])->assertStatus(201);

        // Check that old price record has effective_to = 2026-06-30
        $oldPrice = DealerProductPrice::where('dealer_id', $dealer->id)
            ->where('custom_price', 36.00)
            ->first();
        $this->assertNotNull($oldPrice);
        $this->assertEquals('2026-01-01', $oldPrice->effective_from->toDateString());
        $this->assertEquals('2026-06-30', $oldPrice->effective_to->toDateString());

        // Check that new price record has effective_to = null
        $newPrice = DealerProductPrice::where('dealer_id', $dealer->id)
            ->where('custom_price', 39.00)
            ->first();
        $this->assertNotNull($newPrice);
        $this->assertEquals('2026-07-01', $newPrice->effective_from->toDateString());
        $this->assertNull($newPrice->effective_to);

        // Historical query on 2026-03-15 must return 36.00
        $marchQuery = $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/v1/dealers/{$dealer->id}/prices/resolve?product_id={$this->product20L->id}&date=2026-03-15");
        $marchQuery->assertStatus(200)
            ->assertJsonPath('data.price', 36);

        // Future/Current query on 2026-08-10 must return 39.00
        $augustQuery = $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/v1/dealers/{$dealer->id}/prices/resolve?product_id={$this->product20L->id}&date=2026-08-10");
        $augustQuery->assertStatus(200)
            ->assertJsonPath('data.price', 39);
    }

    public function test_rejects_negative_custom_prices(): void
    {
        $dealer = Dealer::create([
            'code' => 'DLR-NEG',
            'name' => 'Negative Price Dealer',
            'phone' => '+91 98888 12345',
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/dealers/{$dealer->id}/prices", [
                'product_id' => $this->product20L->id,
                'custom_price' => -10.00,
                'effective_from' => '2026-01-01',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['custom_price']);
    }
}
