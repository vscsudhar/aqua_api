<?php

namespace Tests\Feature;

use App\Models\CompanySetting;
use App\Models\Dealer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\TaxRate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CompanySettingsAndTaxTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]);
    }

    public function test_can_retrieve_company_settings(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/company-settings');

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.company_name', 'Marina Aqua Pure Water Co.')
            ->assertJsonPath('data.currency_symbol', '₹')
            ->assertJsonPath('data.currency_code', 'INR')
            ->assertJsonPath('data.default_tax_rate', 18);
    }

    public function test_can_update_company_settings_and_numbering_rules(): void
    {
        $payload = [
            'company_name' => 'Marina Aqua Water Technologies Pvt Ltd',
            'phone' => '+91 94441 99999',
            'email' => 'accounts@marinaaqua.com',
            'invoice_prefix' => 'MAQ',
            'invoice_number_padding' => 5,
            'currency_symbol' => '₹',
            'currency_code' => 'INR',
            'default_tax_rate' => 12.0,
            'gstin' => '33AABCM9999F1Z5',
        ];

        $response = $this->actingAs($this->user)
            ->putJson('/api/v1/company-settings', $payload);

        $response->assertOk()
            ->assertJsonPath('data.company_name', 'Marina Aqua Water Technologies Pvt Ltd')
            ->assertJsonPath('data.invoice_prefix', 'MAQ')
            ->assertJsonPath('data.invoice_number_padding', 5)
            ->assertJsonPath('data.default_tax_rate', 12)
            ->assertJsonPath('data.gstin', '33AABCM9999F1Z5');

        $this->assertDatabaseHas('company_settings', [
            'id' => 1,
            'company_name' => 'Marina Aqua Water Technologies Pvt Ltd',
            'invoice_prefix' => 'MAQ',
        ]);
    }

    public function test_can_upload_valid_company_logo(): void
    {
        Storage::fake('public');

        $file = UploadedFile::fake()->image('company_logo.png', 400, 200);

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/company-settings/logo', [
                'logo' => $file,
            ]);

        $response->assertOk()
            ->assertJsonPath('status', 'success');

        $settings = CompanySetting::getSettings();
        $this->assertNotNull($settings->logo_path);
        Storage::disk('public')->assertExists($settings->logo_path);
    }

    public function test_logo_upload_validates_file_type_and_size_limits(): void
    {
        Storage::fake('public');

        // 1. Invalid file format (text/pdf)
        $invalidFile = UploadedFile::fake()->create('document.pdf', 500, 'application/pdf');

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/company-settings/logo', [
                'logo' => $invalidFile,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['logo']);

        // 2. Oversized file (>2MB)
        $oversizedFile = UploadedFile::fake()->image('large.png')->size(3000); // 3MB

        $response2 = $this->actingAs($this->user)
            ->postJson('/api/v1/company-settings/logo', [
                'logo' => $oversizedFile,
            ]);

        $response2->assertStatus(422)
            ->assertJsonValidationErrors(['logo']);
    }

    public function test_can_delete_uploaded_company_logo(): void
    {
        Storage::fake('public');

        $file = UploadedFile::fake()->image('logo.png');
        $path = $file->storeAs('logos', 'test_logo.png', 'public');
        CompanySetting::getSettings()->update(['logo_path' => $path]);

        $response = $this->actingAs($this->user)
            ->deleteJson('/api/v1/company-settings/logo');

        $response->assertOk()
            ->assertJsonPath('data.logo_path', null);

        $settings = CompanySetting::getSettings();
        $this->assertNull($settings->logo_path);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_can_list_and_filter_tax_rates(): void
    {
        TaxRate::create(['name' => 'GST 5%', 'rate' => 5.0, 'is_active' => true]);
        TaxRate::create(['name' => 'GST 12%', 'rate' => 12.0, 'is_active' => true]);
        TaxRate::create(['name' => 'Old Tax 4%', 'rate' => 4.0, 'is_active' => false]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/tax-rates?is_active=true');

        $response->assertOk()
            ->assertJsonCount(2, 'data');

        $searchResponse = $this->actingAs($this->user)
            ->getJson('/api/v1/tax-rates?search=GST 12%');

        $searchResponse->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'GST 12%');
    }

    public function test_can_create_tax_rate_with_validation(): void
    {
        // 1. Invalid rate (>100 or negative)
        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/tax-rates', [
                'name' => 'Extreme Tax',
                'rate' => 150.0,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['rate']);

        // 2. Valid tax rate
        $validResponse = $this->actingAs($this->user)
            ->postJson('/api/v1/tax-rates', [
                'name' => 'GST 18% Packaged Drinking Water',
                'rate' => 18.0,
                'tax_type' => 'gst',
                'applicability' => 'goods',
                'is_active' => true,
                'is_default' => true,
                'description' => 'Standard rate for 20L cans and PET bottles',
            ]);

        $validResponse->assertStatus(201)
            ->assertJsonPath('data.name', 'GST 18% Packaged Drinking Water')
            ->assertJsonPath('data.rate', 18)
            ->assertJsonPath('data.is_default', true);

        $this->assertDatabaseHas('tax_rates', [
            'name' => 'GST 18% Packaged Drinking Water',
            'rate' => 18.0,
            'is_default' => 1,
        ]);
    }

    public function test_setting_new_default_tax_unsets_previous_default(): void
    {
        $tax1 = TaxRate::create(['name' => 'Tax A', 'rate' => 12.0, 'is_default' => true]);
        $tax2 = TaxRate::create(['name' => 'Tax B', 'rate' => 18.0, 'is_default' => false]);

        $this->actingAs($this->user)
            ->putJson("/api/v1/tax-rates/{$tax2->id}", [
                'is_default' => true,
            ])
            ->assertOk();

        $this->assertFalse($tax1->fresh()->is_default);
        $this->assertTrue($tax2->fresh()->is_default);
        $this->assertEquals(18.0, CompanySetting::getSettings()->default_tax_rate);
    }

    public function test_can_toggle_tax_rate_status(): void
    {
        $tax = TaxRate::create(['name' => 'Seasonal Tax', 'rate' => 8.0, 'is_active' => true]);

        $response = $this->actingAs($this->user)
            ->patchJson("/api/v1/tax-rates/{$tax->id}/toggle-status");

        $response->assertOk()
            ->assertJsonPath('data.is_active', false);

        $this->assertFalse($tax->fresh()->is_active);

        $response2 = $this->actingAs($this->user)
            ->patchJson("/api/v1/tax-rates/{$tax->id}/toggle-status");

        $response2->assertOk()
            ->assertJsonPath('data.is_active', true);

        $this->assertTrue($tax->fresh()->is_active);
    }

    public function test_changing_default_tax_preserves_historical_finalized_invoices(): void
    {
        // 1. Create a finalized historical invoice with snapshot 18% tax
        $dealer = Dealer::create([
            'name' => 'Historical Hotel',
            'code' => 'DLR-HIST',
            'phone' => '9998887776',
            'gstin' => '33AAAAA1111A1Z1',
        ]);

        $invoice = Invoice::create([
            'invoice_no' => 'INV-HIST-001',
            'invoice_date' => '2026-01-15',
            'dealer_id' => $dealer->id,
            'customer_name' => 'Historical Hotel',
            'place_of_supply' => '33-Tamil Nadu',
            'is_interstate' => false,
            'subtotal' => 1000.0,
            'taxable_amount' => 1000.0,
            'cgst_amount' => 90.0,
            'sgst_amount' => 90.0,
            'tax_total' => 180.0,
            'grand_total' => 1180.0,
            'paid_amount' => 1180.0,
            'balance_due' => 0.0,
            'status' => 'finalized',
            'payment_status' => 'paid',
        ]);

        $item = InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'item_description' => '20L Cane Delivery',
            'hsn_sac' => '2201',
            'quantity' => 20,
            'unit' => 'Can',
            'unit_price' => 50.0,
            'taxable_amount' => 1000.0,
            'tax_rate' => 18.0,
            'cgst_rate' => 9.0,
            'cgst_amount' => 90.0,
            'sgst_rate' => 9.0,
            'sgst_amount' => 90.0,
            'line_total' => 1180.0,
        ]);

        // 2. Change the default tax rate in company settings and add new default tax rate of 28%
        $this->actingAs($this->user)
            ->postJson('/api/v1/tax-rates', [
                'name' => 'New Default 28%',
                'rate' => 28.0,
                'is_default' => true,
            ])
            ->assertStatus(201);

        $this->actingAs($this->user)
            ->putJson('/api/v1/company-settings', [
                'default_tax_rate' => 28.0,
            ])
            ->assertOk();

        // 3. Verify historical invoice, items, and tax snapshots remained completely intact
        $freshInvoice = $invoice->fresh(['items']);
        $this->assertEquals(1180.0, (float) $freshInvoice->grand_total);
        $this->assertEquals(180.0, (float) $freshInvoice->tax_total);
        $this->assertEquals(18.0, (float) $freshInvoice->items->first()->tax_rate);
        $this->assertEquals(90.0, (float) $freshInvoice->items->first()->cgst_amount);
        $this->assertEquals(90.0, (float) $freshInvoice->items->first()->sgst_amount);
    }
}
