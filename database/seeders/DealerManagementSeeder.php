<?php

namespace Database\Seeders;

use App\Models\Dealer;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Supply;
use App\Models\SupplyItem;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class DealerManagementSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::first();
        $adminId = $admin?->id ?? 1;
        $today = Carbon::today('Asia/Kolkata');

        // 1. Categories
        $waterCategory = ProductCategory::firstOrCreate(
            ['code' => 'WATER_FINISHED'],
            [
                'name' => 'Packaged Drinking Water',
                'description' => '20L Jars and retail packaged bottles',
                'is_active' => true,
            ]
        );

        $rawCategory = ProductCategory::firstOrCreate(
            ['code' => 'RAW_PACKAGING'],
            [
                'name' => 'Packaging & Raw Materials',
                'description' => 'Caps, preforms, labels, and seals',
                'is_active' => true,
            ]
        );

        // 2. Products
        $jarRefill = Product::firstOrCreate(
            ['sku' => 'MA-20L-REF'],
            [
                'category_id' => $waterCategory->id,
                'name' => '20L Mineral Water Jar (Refill)',
                'unit' => '20L Jar',
                'hsn_code' => '2201',
                'default_price' => 45.00,
                'cost_price' => 18.00,
                'tax_rate' => 18.00,
                'current_stock' => 140.00,
                'min_stock_level' => 40.00,
                'is_active' => true,
            ]
        );

        $jarNew = Product::firstOrCreate(
            ['sku' => 'MA-20L-NEW'],
            [
                'category_id' => $waterCategory->id,
                'name' => '20L New Jar with Mineral Water',
                'unit' => '20L Jar',
                'hsn_code' => '2201',
                'default_price' => 220.00,
                'cost_price' => 120.00,
                'tax_rate' => 18.00,
                'current_stock' => 12.00, // Low stock alert!
                'min_stock_level' => 25.00,
                'is_active' => true,
            ]
        );

        $box1L = Product::firstOrCreate(
            ['sku' => 'MA-1L-BOX'],
            [
                'category_id' => $waterCategory->id,
                'name' => '1L Mineral Water (Case of 12)',
                'unit' => 'Case/12',
                'hsn_code' => '2201',
                'default_price' => 180.00,
                'cost_price' => 95.00,
                'tax_rate' => 18.00,
                'current_stock' => 55.00,
                'min_stock_level' => 30.00,
                'is_active' => true,
            ]
        );

        $caps = Product::firstOrCreate(
            ['sku' => 'MA-CAPS-1K'],
            [
                'category_id' => $rawCategory->id,
                'name' => '20L Blue Seal Caps (Pack of 1000)',
                'unit' => 'Pack/1000',
                'hsn_code' => '3923',
                'default_price' => 420.00,
                'cost_price' => 280.00,
                'tax_rate' => 18.00,
                'current_stock' => 5.00, // Low stock alert!
                'min_stock_level' => 15.00,
                'is_active' => true,
            ]
        );

        // 3. Dealers
        $dealer1 = Dealer::firstOrCreate(
            ['code' => 'DLR-101'],
            [
                'name' => 'Sri Murugan Aqua Distributors',
                'contact_person' => 'K. Murugan',
                'phone' => '+91 94441 23456',
                'email' => 'murugan.aqua@gmail.com',
                'address' => 'No. 12, GST Road, Tambaram, Chennai',
                'gstin' => '33ABCDE1234F1Z5',
                'credit_limit' => 50000.00,
                'opening_balance' => 12500.00,
                'is_active' => true,
            ]
        );

        $dealer2 = Dealer::firstOrCreate(
            ['code' => 'DLR-102'],
            [
                'name' => 'Annai Agencies & Water Supply',
                'contact_person' => 'S. Soundararajan',
                'phone' => '+91 98410 98765',
                'email' => 'annai.agencies@yahoo.com',
                'address' => 'No. 45, Velachery Main Road, Chennai',
                'gstin' => '33XYZPA5678Q1Z2',
                'credit_limit' => 35000.00,
                'opening_balance' => 8200.00,
                'is_active' => true,
            ]
        );

        $dealer3 = Dealer::firstOrCreate(
            ['code' => 'DLR-103'],
            [
                'name' => 'Marina City Water Hub',
                'contact_person' => 'P. Ramesh',
                'phone' => '+91 97900 11223',
                'email' => 'ramesh.marina@gmail.com',
                'address' => 'Shop 4, OMR IT Corridor, Thoraipakkam, Chennai',
                'gstin' => '33AABCM9988K1Z9',
                'credit_limit' => 40000.00,
                'opening_balance' => 4500.00,
                'is_active' => true,
            ]
        );

        // 4. Create Supplies for Today & Past Days if none exist
        if (Supply::count() === 0) {
            // Supply 1 Today to Dealer 1
            $s1 = Supply::create([
                'supply_no' => 'SUP-'.$today->format('Ymd').'-001',
                'dealer_id' => $dealer1->id,
                'supply_date' => $today->format('Y-m-d'),
                'subtotal' => 4500.00,
                'tax_amount' => 810.00,
                'total_amount' => 5310.00,
                'status' => 'delivered',
                'notes' => 'Morning route delivery',
                'created_by' => $adminId,
            ]);

            SupplyItem::create([
                'supply_id' => $s1->id,
                'product_id' => $jarRefill->id,
                'quantity' => 100.00,
                'unit_price' => 45.00,
                'tax_rate' => 18.00,
                'total_amount' => 5310.00,
            ]);

            // Supply 2 Today to Dealer 2
            $s2 = Supply::create([
                'supply_no' => 'SUP-'.$today->format('Ymd').'-002',
                'dealer_id' => $dealer2->id,
                'supply_date' => $today->format('Y-m-d'),
                'subtotal' => 2250.00,
                'tax_amount' => 405.00,
                'total_amount' => 2655.00,
                'status' => 'delivered',
                'notes' => 'Afternoon refill dispatch',
                'created_by' => $adminId,
            ]);

            SupplyItem::create([
                'supply_id' => $s2->id,
                'product_id' => $jarRefill->id,
                'quantity' => 50.00,
                'unit_price' => 45.00,
                'tax_rate' => 18.00,
                'total_amount' => 2655.00,
            ]);

            // Supply 3 Yesterday to Dealer 3
            $yesterday = (clone $today)->subDay();
            $s3 = Supply::create([
                'supply_no' => 'SUP-'.$yesterday->format('Ymd').'-003',
                'dealer_id' => $dealer3->id,
                'supply_date' => $yesterday->format('Y-m-d'),
                'subtotal' => 3600.00,
                'tax_amount' => 648.00,
                'total_amount' => 4248.00,
                'status' => 'delivered',
                'notes' => 'Standard delivery',
                'created_by' => $adminId,
            ]);

            SupplyItem::create([
                'supply_id' => $s3->id,
                'product_id' => $box1L->id,
                'quantity' => 20.00,
                'unit_price' => 180.00,
                'tax_rate' => 18.00,
                'total_amount' => 4248.00,
            ]);
        }

        // 5. Create Payments for Today & Past Days if none exist
        if (Payment::count() === 0) {
            // Payment 1 Today from Dealer 1
            Payment::create([
                'payment_no' => 'RCP-'.$today->format('Ymd').'-001',
                'dealer_id' => $dealer1->id,
                'payment_date' => $today->format('Y-m-d'),
                'amount' => 5000.00,
                'payment_mode' => 'upi',
                'reference_no' => 'UPI9988223311',
                'status' => 'completed',
                'remarks' => 'GPay collection by driver',
                'collected_by' => $adminId,
            ]);

            // Payment 2 Today from Dealer 2
            Payment::create([
                'payment_no' => 'RCP-'.$today->format('Ymd').'-002',
                'dealer_id' => $dealer2->id,
                'payment_date' => $today->format('Y-m-d'),
                'amount' => 2500.00,
                'payment_mode' => 'cash',
                'reference_no' => 'CASH-002',
                'status' => 'completed',
                'remarks' => 'Cash received at warehouse',
                'collected_by' => $adminId,
            ]);

            // Historical payment for chart trend
            for ($d = 2; $d <= 10; $d++) {
                $pastDate = (clone $today)->subDays($d);
                Payment::create([
                    'payment_no' => 'RCP-'.$pastDate->format('Ymd').'-00'.$d,
                    'dealer_id' => ($d % 2 == 0) ? $dealer1->id : $dealer3->id,
                    'payment_date' => $pastDate->format('Y-m-d'),
                    'amount' => 3000.00 + ($d * 250),
                    'payment_mode' => ($d % 3 == 0) ? 'bank_transfer' : (($d % 2 == 0) ? 'upi' : 'cash'),
                    'reference_no' => 'REF-'.$d,
                    'status' => 'completed',
                    'remarks' => 'Collection on route',
                    'collected_by' => $adminId,
                ]);
            }
        }
    }
}
