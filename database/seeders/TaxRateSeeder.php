<?php

namespace Database\Seeders;

use App\Models\CompanySetting;
use App\Models\TaxRate;
use Illuminate\Database\Seeder;

class TaxRateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $taxes = [
            [
                'name' => 'GST 0% (Exempted)',
                'rate' => 0.00,
                'tax_type' => 'exempt',
                'applicability' => 'all',
                'is_active' => true,
                'is_default' => false,
                'description' => 'Unbranded water and zero-rated agricultural / daily essential supplies',
            ],
            [
                'name' => 'GST 5% (Concessional)',
                'rate' => 5.00,
                'tax_type' => 'gst',
                'applicability' => 'all',
                'is_active' => true,
                'is_default' => false,
                'description' => 'Concessional GST rate for designated packaging',
            ],
            [
                'name' => 'GST 12%',
                'rate' => 12.00,
                'tax_type' => 'gst',
                'applicability' => 'all',
                'is_active' => true,
                'is_default' => false,
                'description' => '12% GST slab for intermediate packaging supplies',
            ],
            [
                'name' => 'GST 18% (Standard Packaged Water)',
                'rate' => 18.00,
                'tax_type' => 'gst',
                'applicability' => 'all',
                'is_active' => true,
                'is_default' => true,
                'description' => 'Standard GST for 20L water cans and PET mineral water bottles (HSN 2201)',
            ],
            [
                'name' => 'GST 28% (Aerated Drinks)',
                'rate' => 28.00,
                'tax_type' => 'gst',
                'applicability' => 'all',
                'is_active' => true,
                'is_default' => false,
                'description' => '28% GST for carbonated water and flavored beverages',
            ],
        ];

        foreach ($taxes as $tax) {
            TaxRate::firstOrCreate(
                ['name' => $tax['name']],
                $tax
            );
        }

        // Ensure company settings has default tax set
        CompanySetting::getSettings()->update([
            'default_tax_rate' => 18.00,
            'currency_symbol' => '₹',
            'currency_code' => 'INR',
        ]);
    }
}
