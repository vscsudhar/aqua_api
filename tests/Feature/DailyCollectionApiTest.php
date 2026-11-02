<?php

namespace Tests\Feature;

use App\Models\Dealer;
use App\Models\DealerPayment;
use App\Models\DealerProductPrice;
use App\Models\DealerSupply;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DailyCollectionApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected string $token;

    protected Dealer $dealerA;

    protected Dealer $dealerB;

    protected Product $product20L;

    protected Product $product1L;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'name' => 'Collection Tester',
            'email' => 'collect@marinaaqua.com',
            'username' => 'collecttest',
            'password' => Hash::make('Secret123!'),
            'role' => 'admin',
            'is_active' => true,
        ]);

        $this->token = $this->user->createToken('TestDevice')->plainTextToken;

        $category = ProductCategory::create([
            'name' => 'Packaged Water',
            'code' => 'WATER',
            'is_active' => true,
        ]);

        $this->product20L = Product::create([
            'category_id' => $category->id,
            'name' => '20L Water Jar',
            'sku' => 'MA-20L-JAR',
            'unit' => '20L Jar',
            'default_price' => 50.00,
            'cost_price' => 20.00,
            'tax_rate' => 0.00,
            'current_stock' => 500.00,
            'min_stock_level' => 50.00,
            'is_active' => true,
        ]);

        $this->product1L = Product::create([
            'category_id' => $category->id,
            'name' => '1L Water Bottle Box',
            'sku' => 'MA-1L-BOX',
            'unit' => 'Box',
            'default_price' => 120.00,
            'cost_price' => 60.00,
            'tax_rate' => 0.00,
            'current_stock' => 300.00,
            'min_stock_level' => 30.00,
            'is_active' => true,
        ]);

        $this->dealerA = Dealer::create([
            'code' => 'DLR-AAA',
            'name' => 'Sri Murugan Aqua',
            'contact_person' => 'Murugan',
            'phone' => '+91 98888 11111',
            'credit_limit' => 25000.00,
            'opening_balance' => 500.00,
            'opening_date' => '2026-10-01',
            'is_active' => true,
        ]);
        $this->dealerA->syncOpeningBalanceLedger();

        $this->dealerB = Dealer::create([
            'code' => 'DLR-BBB',
            'name' => 'Velan Enterprises',
            'contact_person' => 'Velan',
            'phone' => '+91 97777 22222',
            'credit_limit' => 15000.00,
            'opening_balance' => 0.00,
            'opening_date' => '2026-10-01',
            'is_active' => true,
        ]);

        // Dealer A has a custom contract price for 20L Jar = 42.00 (Catalog is 50.00)
        DealerProductPrice::create([
            'dealer_id' => $this->dealerA->id,
            'product_id' => $this->product20L->id,
            'custom_price' => 42.00,
            'effective_from' => '2026-10-01',
            'effective_to' => null,
            'is_active' => true,
        ]);
    }

    public function test_create_supply_with_dealer_specific_price_snapshot_and_unpaid_status(): void
    {
        $payload = [
            'dealer_id' => $this->dealerA->id,
            'supply_date' => '2026-10-29',
            'idempotency_key' => 'IDEM-SUP-001',
            'notes' => 'Morning route delivery',
            'items' => [
                [
                    'product_id' => $this->product20L->id,
                    'quantity' => 10,
                    'unit' => '20L Jar',
                ],
                [
                    'product_id' => $this->product1L->id,
                    'quantity' => 5,
                    'unit' => 'Box',
                ],
            ],
        ];

        $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->postJson('/api/v1/supplies', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.payment_status', 'unpaid')
            ->assertJsonPath('data.paid_amount', 0)
            // 10 * 42.00 (dealer custom price) = 420.00
            // 5 * 120.00 (catalog fallback price) = 600.00
            // Total = 1020.00
            ->assertJsonPath('data.total_amount', 1020)
            ->assertJsonPath('data.outstanding_amount', 1020);

        // Verify applied price snapshot was saved
        $supply = DealerSupply::where('idempotency_key', 'IDEM-SUP-001')->first();
        $this->assertNotNull($supply);
        $this->assertCount(2, $supply->items);

        $item20L = $supply->items->where('product_id', $this->product20L->id)->first();
        $this->assertEquals(42.00, (float) $item20L->applied_price);
        $this->assertEquals(50.00, (float) $item20L->catalog_price);
        $this->assertEquals(420.00, (float) $item20L->line_total);

        // Verify ledger entry debit was created
        $ledger = $supply->ledgerEntry;
        $this->assertNotNull($ledger);
        $this->assertEquals(1020.00, (float) $ledger->debit);
        $this->assertEquals(0.00, (float) $ledger->credit);
    }

    public function test_create_supply_with_immediate_full_payment(): void
    {
        $payload = [
            'dealer_id' => $this->dealerA->id,
            'supply_date' => '2026-10-29',
            'idempotency_key' => 'IDEM-FULL-PAY',
            'items' => [
                [
                    'product_id' => $this->product20L->id,
                    'quantity' => 10, // 10 * 42 = 420
                ],
            ],
            'payment_amount' => 420.00,
            'payment_method' => 'upi',
            'payment_reference' => 'UPI-REF-9988',
        ];

        $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->postJson('/api/v1/supplies', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('data.total_amount', 420)
            ->assertJsonPath('data.paid_amount', 420)
            ->assertJsonPath('data.outstanding_amount', 0)
            ->assertJsonPath('data.payment_status', 'paid');

        $supply = DealerSupply::where('idempotency_key', 'IDEM-FULL-PAY')->first();
        $this->assertCount(1, $supply->payments);
        $payment = $supply->payments->first();
        $this->assertEquals(420.00, (float) $payment->amount);
        $this->assertEquals('upi', $payment->payment_method);

        // Check both debit and credit entries in ledger
        $this->assertDatabaseHas('dealer_ledger_entries', [
            'dealer_id' => $this->dealerA->id,
            'entry_type' => 'supply',
            'reference_id' => $supply->id,
            'debit' => 420.00,
        ]);

        $this->assertDatabaseHas('dealer_ledger_entries', [
            'dealer_id' => $this->dealerA->id,
            'entry_type' => 'payment',
            'reference_id' => $payment->id,
            'credit' => 420.00,
        ]);
    }

    public function test_create_supply_with_immediate_partial_payment(): void
    {
        $payload = [
            'dealer_id' => $this->dealerA->id,
            'supply_date' => '2026-10-29',
            'idempotency_key' => 'IDEM-PARTIAL-PAY',
            'items' => [
                [
                    'product_id' => $this->product20L->id,
                    'quantity' => 10, // 10 * 42 = 420
                ],
            ],
            'payment_amount' => 200.00,
            'payment_method' => 'cash',
        ];

        $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->postJson('/api/v1/supplies', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('data.total_amount', 420)
            ->assertJsonPath('data.paid_amount', 200)
            ->assertJsonPath('data.outstanding_amount', 220)
            ->assertJsonPath('data.payment_status', 'partially_paid');
    }

    public function test_subsequent_payment_updates_partially_paid_supply_to_paid(): void
    {
        // 1. Create supply with partial payment of 200 out of 420
        $supply = DealerSupply::create([
            'supply_no' => 'DS-TEST-200',
            'dealer_id' => $this->dealerA->id,
            'supply_date' => '2026-10-29',
            'subtotal' => 420.00,
            'total_amount' => 420.00,
            'paid_amount' => 200.00,
            'outstanding_amount' => 220.00,
            'payment_status' => 'partially_paid',
            'status' => 'delivered',
            'created_by' => $this->user->id,
        ]);
        $supply->syncLedgerEntry($this->user->id);

        DealerPayment::create([
            'payment_no' => 'PAY-TEST-1',
            'dealer_id' => $this->dealerA->id,
            'dealer_supply_id' => $supply->id,
            'payment_date' => '2026-10-29',
            'amount' => 200.00,
            'payment_method' => 'cash',
            'status' => 'completed',
        ])->syncLedgerEntry($this->user->id);

        // 2. Pay remaining 220
        $payPayload = [
            'dealer_id' => $this->dealerA->id,
            'dealer_supply_id' => $supply->id,
            'amount' => 220.00,
            'payment_method' => 'upi',
            'reference_no' => 'UPI-SUBSEQUENT-123',
            'payment_date' => '2026-10-29',
        ];

        $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->postJson('/api/v1/payments', $payPayload);

        $response->assertStatus(201)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.amount', 220);

        // Verify supply status is now fully paid
        $supply->refresh();
        $this->assertEquals(420.00, (float) $supply->paid_amount);
        $this->assertEquals(0.00, (float) $supply->outstanding_amount);
        $this->assertEquals('paid', $supply->payment_status);
    }

    public function test_editing_supply_recalculates_totals_and_updates_ledger(): void
    {
        // Initial supply: 10 jars @ 42 = 420.00, unpaid
        $payload = [
            'dealer_id' => $this->dealerA->id,
            'supply_date' => '2026-10-29',
            'items' => [
                [
                    'product_id' => $this->product20L->id,
                    'quantity' => 10,
                ],
            ],
        ];

        $createRes = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->postJson('/api/v1/supplies', $payload);

        $createRes->assertStatus(201);
        $supplyId = $createRes->json('data.id');

        // Edit supply: Increase quantity to 20 jars @ 42 = 840.00
        $updatePayload = [
            'notes' => 'Updated quantity to 20 jars',
            'items' => [
                [
                    'product_id' => $this->product20L->id,
                    'quantity' => 20,
                ],
            ],
        ];

        $updateRes = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->putJson("/api/v1/supplies/{$supplyId}", $updatePayload);

        $updateRes->assertStatus(200)
            ->assertJsonPath('data.total_amount', 840)
            ->assertJsonPath('data.outstanding_amount', 840)
            ->assertJsonPath('data.notes', 'Updated quantity to 20 jars');

        // Verify ledger entry debit was updated to 840.00
        $this->assertDatabaseHas('dealer_ledger_entries', [
            'dealer_id' => $this->dealerA->id,
            'reference_id' => $supplyId,
            'entry_type' => 'supply',
            'debit' => 840.00,
        ]);
    }

    public function test_idempotent_submission_prevents_duplicate_supplies_and_ledger_entries(): void
    {
        $payload = [
            'dealer_id' => $this->dealerA->id,
            'supply_date' => '2026-10-29',
            'idempotency_key' => 'UNIQUE-TOKEN-XYZ-123',
            'items' => [
                [
                    'product_id' => $this->product20L->id,
                    'quantity' => 10,
                ],
            ],
        ];

        // First call
        $res1 = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->postJson('/api/v1/supplies', $payload);
        $res1->assertStatus(201);
        $firstId = $res1->json('data.id');

        // Repeated call with same idempotency key
        $res2 = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->postJson('/api/v1/supplies', $payload);
        $res2->assertStatus(200); // Or 201 returning same object
        $this->assertEquals($firstId, $res2->json('data.id'));

        // Verify only 1 supply and 1 ledger entry exist
        $this->assertEquals(1, DealerSupply::where('idempotency_key', 'UNIQUE-TOKEN-XYZ-123')->count());
        $this->assertEquals(1, DealerSupply::where('id', $firstId)->first()->ledgerEntry()->count());
    }

    public function test_daily_summary_and_dealers_list_endpoints(): void
    {
        // Create 1 supply for dealer A
        $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->postJson('/api/v1/supplies', [
                'dealer_id' => $this->dealerA->id,
                'supply_date' => '2026-10-29',
                'items' => [
                    ['product_id' => $this->product20L->id, 'quantity' => 10],
                ],
                'payment_amount' => 420.00,
                'payment_method' => 'cash',
            ]);

        // Daily summary
        $summaryRes = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->getJson('/api/v1/collections/daily-summary?date=2026-10-29');

        $summaryRes->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.supplies.total_count', 1)
            ->assertJsonPath('data.supplies.total_amount', 420)
            ->assertJsonPath('data.collections.total_amount', 420)
            ->assertJsonPath('data.collections.by_method.cash', 420)
            ->assertJsonPath('data.dealers_served_count', 1);

        // Daily dealers list
        $dealersRes = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->getJson('/api/v1/collections/dealers?date=2026-10-29');

        $dealersRes->assertStatus(200)
            ->assertJsonPath('status', 'success');

        $data = $dealersRes->json('data');
        $this->assertNotEmpty($data);

        $dealerARecord = collect($data)->firstWhere('code', 'DLR-AAA');
        $this->assertNotNull($dealerARecord);
        $this->assertTrue($dealerARecord['has_supply_today']);
        $this->assertEquals('paid', $dealerARecord['today_status']);
    }
}
