<?php

namespace Database\Seeders;

use App\Models\Dealer;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Models\User;
use App\Services\BillingService;
use App\Services\InventoryService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class SupplierAndPurchaseSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::first();
        $adminId = $admin?->id ?? 1;
        $today = Carbon::today('Asia/Kolkata');

        // 1. Suppliers
        $sup1 = Supplier::firstOrCreate(
            ['code' => 'SUP-001'],
            [
                'name' => 'Apex Polymers & Preforms Ltd',
                'contact_person' => 'Ramesh Narayanan',
                'phone' => '+91 98401 23456',
                'email' => 'sales@apexpolymers.in',
                'address' => 'Plot 45, SIDCO Industrial Estate, Guindy, Chennai',
                'gstin' => '33AABCA1234F1Z1',
                'opening_balance' => 0.00,
                'is_active' => true,
                'notes' => 'Primary preform and bottle manufacturer',
                'created_by' => $adminId,
            ]
        );

        $sup2 = Supplier::firstOrCreate(
            ['code' => 'SUP-002'],
            [
                'name' => 'Crown Caps & Packaging Corp',
                'contact_person' => 'Gopalakrishnan V',
                'phone' => '+91 94440 98765',
                'email' => 'info@crowncaps.com',
                'address' => '12 Ambattur Industrial Estate, Chennai',
                'gstin' => '33XYZPA9876Q1Z9',
                'opening_balance' => 0.00,
                'is_active' => true,
                'notes' => 'Caps, heat shrink sleeves, and seals',
                'created_by' => $adminId,
            ]
        );

        $sup3 = Supplier::firstOrCreate(
            ['code' => 'SUP-003'],
            [
                'name' => 'Aqua Care Filters & Minerals',
                'contact_person' => 'Dr. K. Sundaram',
                'phone' => '+91 98840 55667',
                'email' => 'support@aquacarechennai.com',
                'address' => '77 Velachery Main Road, Chennai',
                'gstin' => '33DEFGH5678R1Z4',
                'opening_balance' => 0.00,
                'is_active' => true,
                'notes' => 'RO membrane, mineral cartridge, ozone consumables',
                'created_by' => $adminId,
            ]
        );

        // 2. Post a couple of initial purchases via InventoryService to populate ledger
        if (Purchase::count() === 0) {
            $inventoryService = app(InventoryService::class);
            $jarRefill = Product::where('sku', 'MA-20L-REF')->first();
            $jarNew = Product::where('sku', 'MA-20L-NEW')->first();
            $caps = Product::where('sku', 'MA-CAPS-1K')->first();

            if ($jarNew && $caps) {
                // Purchase 1 from Apex Polymers
                $inventoryService->postPurchase([
                    'supplier_id' => $sup1->id,
                    'invoice_no' => 'APEX-INV-9021',
                    'purchase_date' => $today->copy()->subDays(3)->toDateString(),
                    'discount_amount' => 200.00,
                    'paid_amount' => 5000.00,
                    'payment_method' => 'bank_transfer',
                    'notes' => 'New 20L jars shipment',
                    'items' => [
                        [
                            'product_id' => $jarNew->id,
                            'quantity' => 50,
                            'unit_cost' => 110.00,
                            'tax_rate' => 18.00,
                        ],
                    ],
                ], $admin);

                // Purchase 2 from Crown Caps
                $inventoryService->postPurchase([
                    'supplier_id' => $sup2->id,
                    'invoice_no' => 'CRW-2026-440',
                    'purchase_date' => $today->copy()->subDay()->toDateString(),
                    'discount_amount' => 50.00,
                    'paid_amount' => 2500.00,
                    'payment_method' => 'upi',
                    'notes' => 'Blue seal caps replenishment',
                    'items' => [
                        [
                            'product_id' => $caps->id,
                            'quantity' => 15,
                            'unit_cost' => 260.00,
                            'tax_rate' => 18.00,
                        ],
                    ],
                ], $admin);
            }
        }

        // 3. Seed sample Invoices if none exist
        if (Invoice::count() === 0) {
            $billingService = app(BillingService::class);
            $dealer1 = Dealer::first();
            $jarRefill = Product::where('sku', 'MA-20L-REF')->first();
            $box1L = Product::where('sku', 'MA-1L-BOX')->first();

            if ($dealer1 && $jarRefill) {
                // Finalized Invoice 1
                $billingService->createInvoice([
                    'dealer_id' => $dealer1->id,
                    'customer_name' => $dealer1->name,
                    'customer_phone' => $dealer1->phone,
                    'customer_address' => $dealer1->address,
                    'customer_gstin' => $dealer1->gstin,
                    'place_of_supply' => '33-Tamil Nadu',
                    'invoice_date' => $today->copy()->subDays(2)->toDateString(),
                    'due_date' => $today->copy()->addDays(13)->toDateString(),
                    'status' => 'finalized',
                    'notes' => 'Bulk supply regular monthly invoice',
                    'items' => [
                        [
                            'product_id' => $jarRefill->id,
                            'item_description' => '20L Mineral Water Jar (Refill)',
                            'hsn_sac' => '2201',
                            'quantity' => 100,
                            'unit' => '20L Jar',
                            'unit_price' => 45.00,
                            'discount_amount' => 100.00,
                            'tax_rate' => 18.00,
                        ],
                        [
                            'product_id' => $box1L?->id,
                            'item_description' => '1L Mineral Water (Case of 12)',
                            'hsn_sac' => '2201',
                            'quantity' => 10,
                            'unit' => 'Case/12',
                            'unit_price' => 180.00,
                            'discount_amount' => 0.00,
                            'tax_rate' => 18.00,
                        ],
                    ],
                ], $admin);

                // Draft Invoice 2
                $billingService->createInvoice([
                    'dealer_id' => $dealer1->id,
                    'customer_name' => 'K. Murugan (Cash Counter)',
                    'customer_phone' => '+91 94441 23456',
                    'place_of_supply' => '33-Tamil Nadu',
                    'invoice_date' => $today->toDateString(),
                    'status' => 'draft',
                    'notes' => 'Draft invoice for weekend festival order',
                    'items' => [
                        [
                            'product_id' => $jarRefill->id,
                            'item_description' => '20L Mineral Water Jar (Refill)',
                            'hsn_sac' => '2201',
                            'quantity' => 40,
                            'unit' => '20L Jar',
                            'unit_price' => 45.00,
                            'discount_amount' => 0.00,
                            'tax_rate' => 18.00,
                        ],
                    ],
                ], $admin);
            }
        }
    }
}
