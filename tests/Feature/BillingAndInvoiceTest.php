<?php

namespace Tests\Feature;

use App\Models\CompanySetting;
use App\Models\Dealer;
use App\Models\DealerLedgerEntry;
use App\Models\DealerSupply;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BillingAndInvoiceTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Dealer $localDealer;

    protected Dealer $interstateDealer;

    protected Product $waterCan;

    protected Product $petBottle;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]);

        // Default Company Setting
        CompanySetting::getSettings();

        $category = ProductCategory::create([
            'name' => 'Packaged Water',
            'code' => 'PKG-WTR',
            'is_active' => true,
        ]);

        $unitCan = Unit::create([
            'name' => '20L Can',
            'code' => 'CAN',
            'is_active' => true,
        ]);

        $this->waterCan = Product::create([
            'category_id' => $category->id,
            'unit_id' => $unitCan->id,
            'name' => '20-Litre Water Can',
            'sku' => 'WTR-CAN-20L',
            'hsn_code' => '2201',
            'unit' => 'Can',
            'default_price' => 50.00,
            'cost_price' => 20.00,
            'tax_rate' => 18.00, // 18% GST
            'current_stock' => 500.00,
            'track_stock' => true,
            'is_active' => true,
        ]);

        $this->petBottle = Product::create([
            'category_id' => $category->id,
            'unit_id' => $unitCan->id,
            'name' => '1L Bottled Water (Box of 12)',
            'sku' => 'WTR-BTL-1L',
            'hsn_code' => '2201',
            'unit' => 'Box',
            'default_price' => 120.00,
            'cost_price' => 60.00,
            'tax_rate' => 12.00, // 12% GST
            'current_stock' => 100.00,
            'track_stock' => true,
            'is_active' => true,
        ]);

        // Local Tamil Nadu Dealer (Intra-state, Code 33)
        $this->localDealer = Dealer::create([
            'code' => 'DLR-TN-01',
            'name' => 'Chennai Fresh Aqua',
            'phone' => '9840111222',
            'address' => '15 Velachery Main Road, Chennai',
            'gstin' => '33AABCT1234F1Z1',
            'state_code' => '33-Tamil Nadu',
            'opening_balance' => 0.00,
            'is_active' => true,
        ]);

        // Interstate Dealer (Bangalore, Karnataka, Code 29)
        $this->interstateDealer = Dealer::create([
            'code' => 'DLR-KA-02',
            'name' => 'Bangalore Metro Beverages',
            'phone' => '9880111222',
            'address' => '44 Indiranagar, Bangalore, Karnataka',
            'gstin' => '29XYZPA5678Q1Z2',
            'state_code' => '29-Karnataka',
            'opening_balance' => 0.00,
            'is_active' => true,
        ]);
    }

    public function test_can_get_and_update_company_settings(): void
    {
        $getRes = $this->actingAs($this->user)->getJson('/api/v1/company-settings');
        $getRes->assertStatus(200)
            ->assertJsonPath('data.company_name', 'Marina Aqua Pure Water Co.')
            ->assertJsonPath('data.state_code', '33');

        $putRes = $this->actingAs($this->user)->putJson('/api/v1/company-settings', [
            'company_name' => 'Marina Aqua Mineral Beverages Pvt Ltd',
            'phone' => '+91 94440 99999',
            'invoice_prefix' => 'MA-INV',
        ]);

        $putRes->assertStatus(200)
            ->assertJsonPath('data.company_name', 'Marina Aqua Mineral Beverages Pvt Ltd')
            ->assertJsonPath('data.invoice_prefix', 'MA-INV');
    }

    public function test_can_create_draft_invoice_with_intra_state_taxes_split_into_cgst_and_sgst(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/v1/invoices', [
            'dealer_id' => $this->localDealer->id,
            'invoice_date' => now()->toDateString(),
            'status' => 'draft',
            'place_of_supply' => '33-Tamil Nadu',
            'items' => [
                [
                    'product_id' => $this->waterCan->id,
                    'item_description' => '20L Water Can Refill',
                    'hsn_sac' => '2201',
                    'quantity' => 100,
                    'unit' => 'Can',
                    'unit_price' => 50.00, // Subtotal = 5000
                    'discount_amount' => 200.00, // Taxable = 4800
                    'tax_rate' => 18.00, // 18% GST -> 9% CGST (432) + 9% SGST (432)
                ],
            ],
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.is_interstate', false)
            ->assertJsonPath('data.subtotal', 5000)
            ->assertJsonPath('data.discount_total', 200)
            ->assertJsonPath('data.taxable_amount', 4800)
            ->assertJsonPath('data.cgst_amount', 432)
            ->assertJsonPath('data.sgst_amount', 432)
            ->assertJsonPath('data.igst_amount', 0)
            ->assertJsonPath('data.tax_total', 864)
            ->assertJsonPath('data.grand_total', 5664)
            ->assertJsonPath('data.status', 'draft');

        $invoiceId = $response->json('data.id');
        $invoice = Invoice::find($invoiceId);
        $this->assertEquals(0, $invoice->payments()->count());
    }

    public function test_can_create_invoice_with_inter_state_tax_assigned_to_igst(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/v1/invoices', [
            'dealer_id' => $this->interstateDealer->id,
            'customer_gstin' => '29XYZPA5678Q1Z2', // Karnataka state code 29
            'place_of_supply' => '29-Karnataka',
            'invoice_date' => now()->toDateString(),
            'status' => 'draft',
            'items' => [
                [
                    'product_id' => $this->petBottle->id,
                    'item_description' => '1L Premium Mineral Water Boxes',
                    'quantity' => 50,
                    'unit_price' => 120.00, // Subtotal = 6000
                    'discount_amount' => 0.00,
                    'tax_rate' => 12.00, // 12% IGST = 720
                ],
            ],
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.is_interstate', true)
            ->assertJsonPath('data.taxable_amount', 6000)
            ->assertJsonPath('data.cgst_amount', 0)
            ->assertJsonPath('data.sgst_amount', 0)
            ->assertJsonPath('data.igst_amount', 720)
            ->assertJsonPath('data.grand_total', 6720);
    }

    public function test_draft_invoice_does_not_affect_dealer_ledger(): void
    {
        $this->actingAs($this->user)->postJson('/api/v1/invoices', [
            'dealer_id' => $this->localDealer->id,
            'invoice_date' => now()->toDateString(),
            'status' => 'draft',
            'items' => [
                [
                    'product_id' => $this->waterCan->id,
                    'item_description' => 'Draft Cans',
                    'quantity' => 20,
                    'unit_price' => 50.00,
                ],
            ],
        ]);

        // Ledger must have 0 entries for this dealer
        $entries = DealerLedgerEntry::where('dealer_id', $this->localDealer->id)->count();
        $this->assertEquals(0, $entries);
    }

    public function test_finalizing_standalone_invoice_debits_dealer_ledger_and_locks_modifications(): void
    {
        // 1. Create draft invoice
        $createRes = $this->actingAs($this->user)->postJson('/api/v1/invoices', [
            'dealer_id' => $this->localDealer->id,
            'invoice_date' => now()->toDateString(),
            'status' => 'draft',
            'items' => [
                [
                    'product_id' => $this->waterCan->id,
                    'item_description' => 'Cans Sale',
                    'quantity' => 40,
                    'unit_price' => 50.00, // 2000 + 18% = 2360
                    'tax_rate' => 18.00,
                ],
            ],
        ]);

        $invoiceId = $createRes->json('data.id');
        $grandTotal = (float) $createRes->json('data.grand_total');

        // 2. Finalize invoice
        $finRes = $this->actingAs($this->user)->postJson("/api/v1/invoices/{$invoiceId}/finalize");
        $finRes->assertStatus(200)
            ->assertJsonPath('data.status', 'finalized');

        // 3. Verify dealer ledger entry was created
        $ledgerEntry = DealerLedgerEntry::where('dealer_id', $this->localDealer->id)
            ->where('entry_type', 'supply')
            ->where('reference_id', $invoiceId)
            ->first();

        $this->assertNotNull($ledgerEntry);
        $this->assertEquals($grandTotal, (float) $ledgerEntry->debit);
        $this->assertEquals($grandTotal, $this->localDealer->calculateOutstandingBalance());

        // 4. Test cannot modify finalized invoice
        $editRes = $this->actingAs($this->user)->putJson("/api/v1/invoices/{$invoiceId}", [
            'items' => [
                [
                    'product_id' => $this->waterCan->id,
                    'item_description' => 'Unauthorized Change',
                    'quantity' => 999,
                    'unit_price' => 10.00,
                ],
            ],
        ]);

        $editRes->assertStatus(422);
    }

    public function test_invoice_derived_from_dealer_supply_does_not_duplicate_ledger_debit(): void
    {
        // 1. Create an existing dealer supply
        $supply = DealerSupply::create([
            'supply_no' => 'DS-20261029-9999',
            'dealer_id' => $this->localDealer->id,
            'supply_date' => now()->toDateString(),
            'total_amount' => 2950.00,
            'status' => 'delivered',
        ]);
        // Supply already debits ledger
        DealerLedgerEntry::create([
            'dealer_id' => $this->localDealer->id,
            'entry_date' => now()->toDateString(),
            'entry_type' => 'supply',
            'reference_id' => $supply->id,
            'reference_no' => $supply->supply_no,
            'debit' => 2950.00,
            'credit' => 0.00,
            'running_balance' => 2950.00,
            'narration' => 'Delivery on Route 2',
        ]);

        $initialLedgerCount = DealerLedgerEntry::where('dealer_id', $this->localDealer->id)->count();

        // 2. Create and finalize a tax invoice linked to that supply
        $invRes = $this->actingAs($this->user)->postJson('/api/v1/invoices', [
            'dealer_id' => $this->localDealer->id,
            'dealer_supply_id' => $supply->id,
            'invoice_date' => now()->toDateString(),
            'status' => 'finalized', // finalized immediately
            'items' => [
                [
                    'product_id' => $this->waterCan->id,
                    'item_description' => '20L Can Delivery Tax Invoice',
                    'quantity' => 50,
                    'unit_price' => 50.00,
                    'tax_rate' => 18.00,
                ],
            ],
        ]);

        $invRes->assertStatus(201)
            ->assertJsonPath('data.dealer_supply_id', $supply->id)
            ->assertJsonPath('data.status', 'finalized');

        // Verify ledger was NOT debited a second time
        $finalLedgerCount = DealerLedgerEntry::where('dealer_id', $this->localDealer->id)->count();
        $this->assertEquals($initialLedgerCount, $finalLedgerCount);
    }

    public function test_cancelling_finalized_invoice_credits_ledger_with_reason(): void
    {
        // 1. Create finalized standalone invoice
        $createRes = $this->actingAs($this->user)->postJson('/api/v1/invoices', [
            'dealer_id' => $this->localDealer->id,
            'invoice_date' => now()->toDateString(),
            'status' => 'finalized',
            'items' => [
                [
                    'product_id' => $this->waterCan->id,
                    'item_description' => 'Cans Sale',
                    'quantity' => 20,
                    'unit_price' => 50.00,
                    'tax_rate' => 18.00,
                ],
            ],
        ]);

        $invoiceId = $createRes->json('data.id');
        $grandTotal = (float) $createRes->json('data.grand_total');
        $this->assertEquals($grandTotal, $this->localDealer->calculateOutstandingBalance());

        // 2. Cancel invoice
        $cancelRes = $this->actingAs($this->user)->postJson("/api/v1/invoices/{$invoiceId}/cancel", [
            'reason' => 'Customer cancelled the event order',
        ]);

        $cancelRes->assertStatus(200)
            ->assertJsonPath('data.status', 'cancelled')
            ->assertJsonPath('data.cancellation_reason', 'Customer cancelled the event order');

        // Ledger balance returned to 0
        $this->assertEquals(0.00, $this->localDealer->calculateOutstandingBalance());
    }

    public function test_credit_note_creation_credits_dealer_ledger(): void
    {
        // 1. Create finalized invoice
        $createRes = $this->actingAs($this->user)->postJson('/api/v1/invoices', [
            'dealer_id' => $this->localDealer->id,
            'invoice_date' => now()->toDateString(),
            'status' => 'finalized',
            'items' => [
                [
                    'product_id' => $this->waterCan->id,
                    'item_description' => 'Cans Sale',
                    'quantity' => 10,
                    'unit_price' => 50.00,
                    'tax_rate' => 18.00,
                ],
            ],
        ]);

        $invoiceId = $createRes->json('data.id');
        $grandTotal = (float) $createRes->json('data.grand_total');

        // 2. Issue credit note for partial return
        $cnRes = $this->actingAs($this->user)->postJson("/api/v1/invoices/{$invoiceId}/credit-note", [
            'subtotal' => 100.00,
            'tax_amount' => 18.00,
            'total_amount' => 118.00,
            'reason' => 'Rate discount concession agreed by director',
        ]);

        $cnRes->assertStatus(201)
            ->assertJsonPath('data.total_amount', 118)
            ->assertJsonPath('data.reason', 'Rate discount concession agreed by director');

        // Dealer outstanding balance reduced by 118
        $this->assertEquals($grandTotal - 118.00, $this->localDealer->calculateOutstandingBalance());
    }

    public function test_recording_payment_on_invoice_updates_paid_amount_and_balance_due(): void
    {
        $createRes = $this->actingAs($this->user)->postJson('/api/v1/invoices', [
            'dealer_id' => $this->localDealer->id,
            'invoice_date' => now()->toDateString(),
            'status' => 'finalized',
            'items' => [
                [
                    'product_id' => $this->waterCan->id,
                    'item_description' => 'Cans Sale',
                    'quantity' => 10,
                    'unit_price' => 50.00,
                    'tax_rate' => 18.00, // 590
                ],
            ],
        ]);

        $invoiceId = $createRes->json('data.id');

        $payRes = $this->actingAs($this->user)->postJson("/api/v1/invoices/{$invoiceId}/payments", [
            'amount' => 590.00,
            'payment_date' => now()->toDateString(),
            'payment_method' => 'upi',
            'reference_no' => 'UPI9988776655',
        ]);

        $payRes->assertStatus(201)
            ->assertJsonPath('invoice.payment_status', 'paid')
            ->assertJsonPath('invoice.paid_amount', 590)
            ->assertJsonPath('invoice.balance_due', 0);
    }

    public function test_can_generate_pdf_invoice(): void
    {
        $createRes = $this->actingAs($this->user)->postJson('/api/v1/invoices', [
            'dealer_id' => $this->localDealer->id,
            'invoice_date' => now()->toDateString(),
            'status' => 'finalized',
            'items' => [
                [
                    'product_id' => $this->waterCan->id,
                    'item_description' => '20L Pure Water Jar',
                    'quantity' => 20,
                    'unit_price' => 50.00,
                    'tax_rate' => 18.00,
                ],
            ],
        ]);

        $invoiceId = $createRes->json('data.id');

        $pdfRes = $this->actingAs($this->user)->get("/api/v1/invoices/{$invoiceId}/pdf");
        $pdfRes->assertStatus(200)
            ->assertHeader('content-type', 'application/pdf');

        $this->assertNotEmpty($pdfRes->getContent());
    }

    public function test_invoice_creation_handles_soft_deleted_invoices_without_duplicate_number_conflict(): void
    {
        $today = now()->toDateString();

        // 1. Create first invoice
        $res1 = $this->actingAs($this->user)->postJson('/api/v1/invoices', [
            'dealer_id' => $this->localDealer->id,
            'invoice_date' => $today,
            'items' => [
                [
                    'product_id' => $this->waterCan->id,
                    'item_description' => 'First Invoice Item',
                    'quantity' => 1,
                    'unit_price' => 50.00,
                    'tax_rate' => 18.00,
                ],
            ],
        ]);
        $res1->assertStatus(201);
        $invoice1Id = $res1->json('data.id');
        $invoice1No = $res1->json('data.invoice_no');

        // 2. Soft delete first invoice
        $inv1 = Invoice::find($invoice1Id);
        $inv1->delete();
        $this->assertSoftDeleted('invoices', ['id' => $invoice1Id]);

        // 3. Create second invoice on the same date
        $res2 = $this->actingAs($this->user)->postJson('/api/v1/invoices', [
            'dealer_id' => $this->localDealer->id,
            'invoice_date' => $today,
            'items' => [
                [
                    'product_id' => $this->waterCan->id,
                    'item_description' => 'Second Invoice Item',
                    'quantity' => 2,
                    'unit_price' => 50.00,
                    'tax_rate' => 18.00,
                ],
            ],
        ]);

        $res2->assertStatus(201);
        $invoice2No = $res2->json('data.invoice_no');

        // Verify that the second invoice got a distinct, incremented invoice number
        $this->assertNotEquals($invoice1No, $invoice2No);
    }
}
