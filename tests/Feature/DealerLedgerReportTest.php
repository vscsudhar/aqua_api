<?php

namespace Tests\Feature;

use App\Models\Dealer;
use App\Models\DealerLedgerEntry;
use App\Models\DealerPayment;
use App\Models\DealerSupply;
use App\Models\DealerSupplyItem;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DealerLedgerReportTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Dealer $dealer;

    protected Product $caneProduct;

    protected Product $bottleProduct;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $category = ProductCategory::create(['name' => 'Water Containers', 'code' => 'WATER']);
        $canUnit = Unit::create(['name' => 'Can', 'code' => 'CAN']);
        $botUnit = Unit::create(['name' => 'Bottle', 'code' => 'BOT']);

        $this->caneProduct = Product::create([
            'category_id' => $category->id,
            'unit_id' => $canUnit->id,
            'name' => '20L Packaged Water Can',
            'sku' => 'MA-20L-CAN',
            'default_price' => 50.00,
            'cost_price' => 20.00,
            'tax_rate' => 0.00,
            'current_stock' => 1000,
            'min_stock_level' => 100,
            'track_stock' => true,
            'is_active' => true,
        ]);

        $this->bottleProduct = Product::create([
            'category_id' => $category->id,
            'unit_id' => $botUnit->id,
            'name' => '500ml Bottled Water',
            'sku' => 'MA-500ML',
            'default_price' => 10.00,
            'cost_price' => 4.00,
            'tax_rate' => 0.00,
            'current_stock' => 5000,
            'min_stock_level' => 200,
            'track_stock' => true,
            'is_active' => true,
        ]);

        $this->dealer = Dealer::create([
            'code' => 'DLR-LEDGER-01',
            'name' => 'Adyar Waters',
            'phone' => '+91 98765 43210',
            'opening_balance' => 1000.00,
            'opening_date' => '2026-09-01',
            'is_active' => true,
        ]);
        $this->dealer->syncOpeningBalanceLedger($this->user->id);
    }

    public function test_can_fetch_monthly_ledger_matrix_structure(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/reports/dealer-ledger?year=2026&month=10');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'company',
                    'report_meta' => ['title', 'month', 'year', 'month_name', 'days_in_month', 'cane_product'],
                    'dealers',
                    'dates',
                    'period1_summary',
                    'period2_summary',
                    'month_summary',
                    'product_breakdown',
                ],
            ]);

        $this->assertEquals(31, count($response->json('data.dates')));
    }

    public function test_handles_february_leap_and_non_leap_years(): void
    {
        // 2024 is a leap year (29 days)
        $respLeap = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/reports/dealer-ledger?year=2024&month=2');
        $respLeap->assertStatus(200);
        $this->assertEquals(29, $respLeap->json('data.report_meta.days_in_month'));
        $this->assertEquals(29, count($respLeap->json('data.dates')));

        // 2025 is a non-leap year (28 days)
        $respNonLeap = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/reports/dealer-ledger?year=2025&month=2');
        $respNonLeap->assertStatus(200);
        $this->assertEquals(28, $respNonLeap->json('data.report_meta.days_in_month'));
        $this->assertEquals(28, count($respNonLeap->json('data.dates')));
    }

    public function test_period1_closing_balance_carries_forward_to_period2_opening_balance(): void
    {
        // P1 supply on Oct 5: 10 cans = 500
        $supplyP1 = DealerSupply::create([
            'dealer_id' => $this->dealer->id,
            'supply_no' => 'DS-20261005-0001',
            'supply_date' => '2026-10-05',
            'subtotal' => 500.00,
            'total_amount' => 500.00,
            'status' => 'delivered',
            'payment_status' => 'unpaid',
        ]);
        DealerSupplyItem::create([
            'dealer_supply_id' => $supplyP1->id,
            'product_id' => $this->caneProduct->id,
            'product_name' => $this->caneProduct->name,
            'product_sku' => $this->caneProduct->sku,
            'unit' => 'CAN',
            'quantity' => 10,
            'catalog_price' => 50.00,
            'applied_price' => 50.00,
            'line_total' => 500.00,
        ]);
        $supplyP1->syncLedgerEntry($this->user->id);

        // P1 payment on Oct 10: 300
        $payP1 = DealerPayment::create([
            'dealer_id' => $this->dealer->id,
            'dealer_supply_id' => $supplyP1->id,
            'payment_no' => 'PAY-20261010-0001',
            'payment_date' => '2026-10-10',
            'amount' => 300.00,
            'payment_method' => 'cash',
            'status' => 'completed',
        ]);
        $payP1->syncLedgerEntry($this->user->id);

        // P2 supply on Oct 20: 20 cans = 1000
        $supplyP2 = DealerSupply::create([
            'dealer_id' => $this->dealer->id,
            'supply_no' => 'DS-20261020-0001',
            'supply_date' => '2026-10-20',
            'subtotal' => 1000.00,
            'total_amount' => 1000.00,
            'status' => 'delivered',
            'payment_status' => 'unpaid',
        ]);
        DealerSupplyItem::create([
            'dealer_supply_id' => $supplyP2->id,
            'product_id' => $this->caneProduct->id,
            'product_name' => $this->caneProduct->name,
            'product_sku' => $this->caneProduct->sku,
            'unit' => 'CAN',
            'quantity' => 20,
            'catalog_price' => 50.00,
            'applied_price' => 50.00,
            'line_total' => 1000.00,
        ]);
        $supplyP2->syncLedgerEntry($this->user->id);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/reports/dealer-ledger?year=2026&month=10');

        $response->assertStatus(200);
        $dId = $this->dealer->id;

        $p1Closing = (float) $response->json("data.period1_summary.dealers.{$dId}.closing_balance");
        $p2Opening = (float) $response->json("data.period2_summary.dealers.{$dId}.opening_balance");

        // Period 1 calculation:
        // Opening = 1000, Filling = 500, Due = 1500, Paid = 300 => Closing = 1200
        $this->assertEquals(1200.00, $p1Closing);
        $this->assertEquals(1200.00, $p2Opening);
        $this->assertEquals($p1Closing, $p2Opening, 'Period 1 closing balance MUST equal Period 2 opening balance');

        // Period 2 calculation:
        // Opening = 1200, Filling = 1000, Due = 2200, Paid = 0 => Closing = 2200
        $p2Closing = (float) $response->json("data.period2_summary.dealers.{$dId}.closing_balance");
        $monthClosing = (float) $response->json("data.month_summary.dealers.{$dId}.month_closing_balance");

        $this->assertEquals(2200.00, $p2Closing);
        $this->assertEquals(2200.00, $monthClosing);
    }

    public function test_previous_month_closing_becomes_new_month_opening(): void
    {
        // Activity in September: Supply on Sept 15: 10 cans = 500
        $supplySep = DealerSupply::create([
            'dealer_id' => $this->dealer->id,
            'supply_no' => 'DS-20260915-0001',
            'supply_date' => '2026-09-15',
            'subtotal' => 500.00,
            'total_amount' => 500.00,
            'status' => 'delivered',
            'payment_status' => 'unpaid',
        ]);
        DealerSupplyItem::create([
            'dealer_supply_id' => $supplySep->id,
            'product_id' => $this->caneProduct->id,
            'product_name' => $this->caneProduct->name,
            'product_sku' => $this->caneProduct->sku,
            'unit' => 'CAN',
            'quantity' => 10,
            'catalog_price' => 50.00,
            'applied_price' => 50.00,
            'line_total' => 500.00,
        ]);
        $supplySep->syncLedgerEntry($this->user->id);

        // Sep Report
        $respSep = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/reports/dealer-ledger?year=2026&month=9');
        $respSep->assertStatus(200);

        $dId = $this->dealer->id;
        $sepClosing = (float) $respSep->json("data.month_summary.dealers.{$dId}.month_closing_balance");
        // Opening 1000 + 500 = 1500
        $this->assertEquals(1500.00, $sepClosing);

        // Oct Report
        $respOct = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/reports/dealer-ledger?year=2026&month=10');
        $respOct->assertStatus(200);

        $octOpening = (float) $respOct->json("data.month_summary.dealers.{$dId}.month_opening_balance");
        $this->assertEquals($sepClosing, $octOpening, "October's opening balance must match September's closing balance");
    }

    public function test_reconciliation_against_daily_collections(): void
    {
        // Supply on Oct 2
        $supply = DealerSupply::create([
            'dealer_id' => $this->dealer->id,
            'supply_no' => 'DS-20261002-0001',
            'supply_date' => '2026-10-02',
            'subtotal' => 750.00,
            'total_amount' => 750.00,
            'status' => 'delivered',
            'payment_status' => 'unpaid',
        ]);
        DealerSupplyItem::create([
            'dealer_supply_id' => $supply->id,
            'product_id' => $this->caneProduct->id,
            'product_name' => $this->caneProduct->name,
            'product_sku' => $this->caneProduct->sku,
            'unit' => 'CAN',
            'quantity' => 15,
            'catalog_price' => 50.00,
            'applied_price' => 50.00,
            'line_total' => 750.00,
        ]);
        $supply->syncLedgerEntry($this->user->id);

        // Payment on Oct 2
        $pay = DealerPayment::create([
            'dealer_id' => $this->dealer->id,
            'dealer_supply_id' => $supply->id,
            'payment_no' => 'PAY-20261002-0001',
            'payment_date' => '2026-10-02',
            'amount' => 500.00,
            'payment_method' => 'upi',
            'status' => 'completed',
        ]);
        $pay->syncLedgerEntry($this->user->id);

        // Daily Collection summary endpoint
        $colResp = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/collections/daily-summary?date=2026-10-02');
        $colResp->assertStatus(200);
        $colTotalAmount = (float) $colResp->json('data.supplies.total_amount');
        $colPaidAmount = (float) $colResp->json('data.collections.total_amount');

        // Ledger matrix report
        $ledgerResp = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/reports/dealer-ledger?year=2026&month=10');
        $ledgerResp->assertStatus(200);

        // Day 2 (index 1) in dates
        $day2 = $ledgerResp->json('data.dates.1');
        $this->assertEquals('2026-10-02', $day2['date']);
        $this->assertEquals($colTotalAmount, (float) $day2['totals']['filling_amount']);
        $this->assertEquals($colPaidAmount, (float) $day2['totals']['payments_received']);
    }

    public function test_others_entry_creation_and_inclusion_in_report(): void
    {
        // Store an Others transaction for Oct 7
        $postResp = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/reports/dealer-ledger/others', [
                'transaction_date' => '2026-10-07',
                'cane_quantity' => 25,
                'filling_amount' => 1250.00,
                'payments_received' => 1250.00,
                'notes' => 'Direct counter sales to walk-in customers',
            ]);
        $postResp->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.cane_quantity', 25)
            ->assertJsonPath('data.period', 1);

        // Report reflects Others
        $ledgerResp = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/reports/dealer-ledger?year=2026&month=10');
        $ledgerResp->assertStatus(200);

        $day7 = $ledgerResp->json('data.dates.6');
        $this->assertEquals(25, $day7['others']['cane_qty']);
        $this->assertEquals(1250.00, (float) $day7['others']['filling_amount']);
        $this->assertEquals(1250.00, (float) $day7['others']['payments_received']);

        $this->assertGreaterThanOrEqual(25, $ledgerResp->json('data.period1_summary.others.total_canes'));
        $this->assertGreaterThanOrEqual(1250.00, (float) $ledgerResp->json('data.period1_summary.others.total_filling'));
    }

    public function test_distinct_product_breakdown_never_combines_sizes(): void
    {
        $supply = DealerSupply::create([
            'dealer_id' => $this->dealer->id,
            'supply_no' => 'DS-20261015-0001',
            'supply_date' => '2026-10-15',
            'subtotal' => 700.00,
            'total_amount' => 700.00,
            'status' => 'delivered',
            'payment_status' => 'unpaid',
        ]);

        // 10 20L cans
        DealerSupplyItem::create([
            'dealer_supply_id' => $supply->id,
            'product_id' => $this->caneProduct->id,
            'product_name' => $this->caneProduct->name,
            'product_sku' => $this->caneProduct->sku,
            'unit' => 'CAN',
            'quantity' => 10,
            'catalog_price' => 50.00,
            'applied_price' => 50.00,
            'line_total' => 500.00,
        ]);

        // 20 500ml bottles
        DealerSupplyItem::create([
            'dealer_supply_id' => $supply->id,
            'product_id' => $this->bottleProduct->id,
            'product_name' => $this->bottleProduct->name,
            'product_sku' => $this->bottleProduct->sku,
            'unit' => 'BOT',
            'quantity' => 20,
            'catalog_price' => 10.00,
            'applied_price' => 10.00,
            'line_total' => 200.00,
        ]);
        $supply->syncLedgerEntry($this->user->id);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/reports/dealer-ledger?year=2026&month=10');

        $response->assertStatus(200);

        // Day 15 cane count should be 10, NOT 30!
        $day15 = $response->json('data.dates.14');
        $dId = $this->dealer->id;
        $this->assertEquals(10, $day15['dealers'][$dId]['cane_qty'], 'Bottle counts must never be combined with cane count');

        // Product breakdown has 2 distinct records
        $breakdown = $response->json('data.product_breakdown');
        $this->assertCount(2, $breakdown);

        $caneEntry = collect($breakdown)->firstWhere('product_id', $this->caneProduct->id);
        $bottleEntry = collect($breakdown)->firstWhere('product_id', $this->bottleProduct->id);

        $this->assertEquals(10, $caneEntry['total_quantity']);
        $this->assertEquals(20, $bottleEntry['total_quantity']);
    }

    public function test_export_pdf_and_excel_endpoints(): void
    {
        // PDF Export
        $pdfResp = $this->actingAs($this->user, 'sanctum')
            ->get('/api/v1/reports/dealer-ledger/export-pdf?year=2026&month=10');
        $pdfResp->assertStatus(200);
        $this->assertTrue(str_contains($pdfResp->headers->get('content-type'), 'pdf'));

        // Excel Export
        $excelResp = $this->actingAs($this->user, 'sanctum')
            ->get('/api/v1/reports/dealer-ledger/export-excel?year=2026&month=10');
        $excelResp->assertStatus(200);
        $this->assertTrue(str_contains($excelResp->headers->get('content-type'), 'spreadsheetml.sheet'));

        // CSV Export
        $csvResp = $this->actingAs($this->user, 'sanctum')
            ->get('/api/v1/reports/dealer-ledger/export-csv?year=2026&month=10');
        $csvResp->assertStatus(200);
        $this->assertTrue(str_contains($csvResp->headers->get('content-type'), 'text/csv'));
    }

    public function test_daily_collection_refill_20l_updates_dealer_ledger_p1_total_cane_and_p1_filling(): void
    {
        // 1. Create a 20Litre(refill) product
        $refill20L = Product::create([
            'category_id' => $this->caneProduct->category_id,
            'name' => '20Litre(refill)',
            'sku' => '20L-REFILL-CUSTOM',
            'unit' => 'Litre',
            'default_price' => 35.00,
            'cost_price' => 15.00,
            'tax_rate' => 0.00,
            'current_stock' => 500,
            'is_active' => true,
        ]);

        // 2. Add supply in Period 1 (Day 10) for dealer with 15 canes
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/supplies', [
                'dealer_id' => $this->dealer->id,
                'supply_date' => '2026-10-10',
                'items' => [
                    [
                        'product_id' => $refill20L->id,
                        'quantity' => 15,
                        'custom_price' => 35.00,
                    ],
                ],
                'payment_amount' => 300.00,
                'payment_method' => 'cash',
            ]);
        $response->assertStatus(201);

        // 3. Fetch monthly ledger report
        $reportResp = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/reports/dealer-ledger?year=2026&month=10');
        $reportResp->assertStatus(200);

        $dId = $this->dealer->id;
        $p1Dealer = $reportResp->json("data.period1_summary.dealers.{$dId}");

        // Assert P1 Total Canes includes the 15 canes
        $this->assertEquals(15, $p1Dealer['total_canes']);

        // Assert P1 Filling includes the 525.00 amount (15 * 35.00)
        $this->assertEquals(525.00, (float) $p1Dealer['total_filling']);

        // Assert P1 Total Due equals opening balance + total filling
        $expectedDue = round((float) $p1Dealer['opening_balance'] + 525.00, 2);
        $this->assertEquals($expectedDue, (float) $p1Dealer['total_due']);

        // Assert Day 10 matrix row has cane_qty = 15 and filling_amount = 525.00
        $day10 = collect($reportResp->json('data.dates'))->firstWhere('day', 10);
        $this->assertNotNull($day10);
        $this->assertEquals(15, $day10['dealers'][$dId]['cane_qty']);
        $this->assertEquals(525.00, (float) $day10['dealers'][$dId]['filling_amount']);
    }

    public function test_refill_product_type_recognized_for_dealer_ledger_even_if_refill_word_missing_in_name(): void
    {
        $service = app(\App\Services\DealerLedgerReportService::class);

        // Case 1: product_type == 'refill' with arbitrary name (no 'refill' or '20' word)
        $this->assertTrue(
            $service->isRefillCaneProduct(null, '10litre', 'SKU-10L', 'LTR', null, 'refill'),
            'Product with product_type == refill must be considered for dealer ledger even if word refill is missing in name'
        );

        // Case 2: Name contains 'refill' even if product_type is null or normal
        $this->assertTrue(
            $service->isRefillCaneProduct(null, 'Water Refill Item', 'SKU-001', 'NOS', null, 'normal'),
            'Product with refill in name must be considered even if product_type is normal'
        );

        // Case 3: Normal non-refill product without refill keywords
        $this->assertFalse(
            $service->isRefillCaneProduct(null, '500ml Bottle Pack', 'BOT-500', 'BOX', null, 'normal'),
            'Normal non-refill bottled product must not be considered'
        );
    }

    public function test_dealer_ledger_calculation_flow_for_p1_and_p2_with_taxable_product_and_partial_payments(): void
    {
        // 1. Create a product with tax_rate = 18.00%
        $taxableCan = Product::create([
            'category_id' => $this->caneProduct->category_id,
            'name' => '20L Taxable Water Can',
            'sku' => '20L-TAX-CAN',
            'unit' => 'Can',
            'default_price' => 20.00,
            'cost_price' => 10.00,
            'tax_rate' => 18.00, // Product has 18% tax in catalog
            'current_stock' => 1000,
            'is_active' => true,
        ]);

        // Dealer starts with opening balance of 1000.00
        $dId = $this->dealer->id;

        // 2. PERIOD 1: Dealer fills 60 canes @ 20.00 = 1,200.00, pays 200.00 on Day 5
        $respP1 = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/supplies', [
                'dealer_id' => $dId,
                'supply_date' => '2026-10-05',
                'items' => [
                    [
                        'product_id' => $taxableCan->id,
                        'quantity' => 60,
                        'custom_price' => 20.00,
                    ],
                ],
                'payment_amount' => 200.00,
                'payment_method' => 'cash',
            ]);
        $respP1->assertStatus(201);
        // Assert backend supply did not add 18% tax on top of flat dealer billing
        $this->assertEquals(1200.00, (float) $respP1->json('data.total_amount'));
        $this->assertEquals(0.00, (float) $respP1->json('data.tax_amount'));

        // Verify P1 report
        $report1 = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/reports/dealer-ledger?year=2026&month=10')
            ->assertStatus(200);

        $p1Dealer = $report1->json("data.period1_summary.dealers.{$dId}");
        $this->assertEquals(60, $p1Dealer['total_canes']);
        $this->assertEquals(1200.00, (float) $p1Dealer['total_filling']);
        $this->assertEquals(2200.00, (float) $p1Dealer['total_due']); // 1000 + 1200
        $this->assertEquals(200.00, (float) $p1Dealer['total_payments']);
        $this->assertEquals(2000.00, (float) $p1Dealer['closing_balance']); // 2200 - 200

        // P2 opening balance MUST equal P1 closing balance
        $p2Dealer = $report1->json("data.period2_summary.dealers.{$dId}");
        $this->assertEquals(2000.00, (float) $p2Dealer['opening_balance']);

        // 3. PERIOD 2: Dealer fills 60 canes @ 20.00 = 1,200.00, pays 200.00 on Day 18
        $respP2 = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/supplies', [
                'dealer_id' => $dId,
                'supply_date' => '2026-10-18',
                'items' => [
                    [
                        'product_id' => $taxableCan->id,
                        'quantity' => 60,
                        'custom_price' => 20.00,
                    ],
                ],
                'payment_amount' => 200.00,
                'payment_method' => 'cash',
            ]);
        $respP2->assertStatus(201);
        $this->assertEquals(1200.00, (float) $respP2->json('data.total_amount'));

        // Verify P2 report after first transaction
        $report2 = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/reports/dealer-ledger?year=2026&month=10')
            ->assertStatus(200);

        $p2Dealer = $report2->json("data.period2_summary.dealers.{$dId}");
        $this->assertEquals(60, $p2Dealer['total_canes']);
        $this->assertEquals(1200.00, (float) $p2Dealer['total_filling']);
        $this->assertEquals(3200.00, (float) $p2Dealer['total_due']); // 2000 (P2 Op) + 1200
        $this->assertEquals(200.00, (float) $p2Dealer['total_payments']);
        $this->assertEquals(3000.00, (float) $p2Dealer['closing_balance']); // 3200 - 200

        // 4. PERIOD 2: Subsequent transaction with partial payment and bill
        // Dealer fills 40 canes on Day 22 @ 20.00 = 800.00, pays 300.00
        $respP2_2 = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/supplies', [
                'dealer_id' => $dId,
                'supply_date' => '2026-10-22',
                'items' => [
                    [
                        'product_id' => $taxableCan->id,
                        'quantity' => 40,
                        'custom_price' => 20.00,
                    ],
                ],
                'payment_amount' => 300.00,
                'payment_method' => 'upi',
            ]);
        $respP2_2->assertStatus(201);

        // Additional standalone payment on Day 25: 500.00
        $respPay = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/payments', [
                'dealer_id' => $dId,
                'payment_date' => '2026-10-25',
                'amount' => 500.00,
                'payment_method' => 'bank_transfer',
            ]);
        $respPay->assertStatus(201);

        // Verify final P2 summary and Month Grand Totals
        $report3 = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/reports/dealer-ledger?year=2026&month=10')
            ->assertStatus(200);

        $p2Final = $report3->json("data.period2_summary.dealers.{$dId}");
        // Total Canes = 60 + 40 = 100
        $this->assertEquals(100, $p2Final['total_canes']);
        // Total Filling = 1200 + 800 = 2000.00
        $this->assertEquals(2000.00, (float) $p2Final['total_filling']);
        // Total Due = P2 Op (2000.00) + Total Filling (2000.00) = 4000.00
        $this->assertEquals(4000.00, (float) $p2Final['total_due']);
        // Total Payments = 200 + 300 + 500 = 1000.00
        $this->assertEquals(1000.00, (float) $p2Final['total_payments']);
        // Closing Balance = 4000.00 - 1000.00 = 3000.00
        $this->assertEquals(3000.00, (float) $p2Final['closing_balance']);

        // Month Grand Totals
        $monthDealer = $report3->json("data.month_summary.dealers.{$dId}");
        $this->assertEquals(1000.00, (float) $monthDealer['month_opening_balance']);
        $this->assertEquals(160, $monthDealer['total_canes']); // 60 (P1) + 100 (P2)
        $this->assertEquals(3200.00, (float) $monthDealer['total_filling']); // 1200 + 2000
        $this->assertEquals(1200.00, (float) $monthDealer['total_payments']); // 200 + 1000
        $this->assertEquals(3000.00, (float) $monthDealer['month_closing_balance']); // 1000 + 3200 - 1200

        // Ensure no duplicate ledger entries exist
        $ledgerEntries = DealerLedgerEntry::where('dealer_id', $dId)->orderBy('id')->get();
        // 1 opening balance + 3 supplies + 4 payments (3 immediate + 1 standalone) = 8 entries
        $this->assertCount(8, $ledgerEntries);
        // Check closing balance matches ledger running balance
        $totalDebits = (float) $ledgerEntries->sum('debit');
        $totalCredits = (float) $ledgerEntries->sum('credit');
        $this->assertEquals(4200.00, $totalDebits);
        $this->assertEquals(1200.00, $totalCredits);
        $this->assertEquals(3000.00, $totalDebits - $totalCredits);
    }
}
