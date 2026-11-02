<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Unit;
use Illuminate\Database\Seeder;

class UnitAndProductSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Core Measurement Units
        $units = [
            ['name' => 'Litre', 'code' => 'LTR', 'description' => 'Volume in Litres'],
            ['name' => 'Millilitre', 'code' => 'ML', 'description' => 'Volume in Millilitres'],
            ['name' => 'Numbers', 'code' => 'NOS', 'description' => 'Discrete individual units'],
            ['name' => 'Kilogram', 'code' => 'KG', 'description' => 'Weight in Kilograms'],
            ['name' => 'Pack', 'code' => 'PACK', 'description' => 'Bundled package / shrink-wrap'],
            ['name' => 'Can / Jar', 'code' => 'CAN', 'description' => '20-Litre bulk water containers'],
            ['name' => 'Box', 'code' => 'BOX', 'description' => 'Corrugated cardboard box packaging'],
        ];

        $unitMap = [];
        foreach ($units as $u) {
            $unitMap[$u['code']] = Unit::firstOrCreate(['code' => $u['code']], $u);
        }

        // 2. Product Categories
        $finishedWater = ProductCategory::firstOrCreate(
            ['code' => 'WATER_FINISHED'],
            [
                'name' => 'Packaged Drinking Water',
                'description' => '20-Litre cans and sealed mineral water bottles',
                'is_active' => true,
            ]
        );

        $packaging = ProductCategory::firstOrCreate(
            ['code' => 'RAW_PACKAGING'],
            [
                'name' => 'Packaging Materials & Consumables',
                'description' => 'Preforms, caps, cardboard boxes, and plastics',
                'is_active' => true,
            ]
        );

        // 3. Example Products
        $products = [
            [
                'category_id' => $finishedWater->id,
                'unit_id' => $unitMap['CAN']->id,
                'name' => '20-Litre Water Can (Refill)',
                'sku' => 'MA-CAN-20L',
                'unit' => '20L Can',
                'description' => '20-Litre purified mineral water refill for commercial and residential dispensers.',
                'hsn_code' => '2201',
                'default_price' => 45.00,
                'cost_price' => 18.00,
                'tax_rate' => 18.00,
                'current_stock' => 120.00,
                'min_stock_level' => 40.00,
                'track_stock' => true,
                'is_active' => true,
            ],
            [
                'category_id' => $finishedWater->id,
                'unit_id' => $unitMap['CAN']->id,
                'name' => '20-Litre New Water Can with Water',
                'sku' => 'MA-CAN-20L-NEW',
                'unit' => '20L Can',
                'description' => 'Brand new heavy-duty food-grade PET 20L can with initial water filling.',
                'hsn_code' => '2201',
                'default_price' => 220.00,
                'cost_price' => 130.00,
                'tax_rate' => 18.00,
                'current_stock' => 15.00,
                'min_stock_level' => 25.00,
                'track_stock' => true,
                'is_active' => true,
            ],
            [
                'category_id' => $finishedWater->id,
                'unit_id' => $unitMap['BOX']->id,
                'name' => '2L Packaged Mineral Water (Case of 9)',
                'sku' => 'MA-BOT-2L',
                'unit' => 'Box/9',
                'description' => '2 Litre family hydration bottles packed in heavy corrugated master cartons.',
                'hsn_code' => '2201',
                'default_price' => 270.00,
                'cost_price' => 140.00,
                'tax_rate' => 18.00,
                'current_stock' => 60.00,
                'min_stock_level' => 30.00,
                'track_stock' => true,
                'is_active' => true,
            ],
            [
                'category_id' => $finishedWater->id,
                'unit_id' => $unitMap['BOX']->id,
                'name' => '1L Packaged Mineral Water (Case of 12)',
                'sku' => 'MA-BOT-1L',
                'unit' => 'Box/12',
                'description' => 'Standard 1000ml pure mineral drinking water bottles for retail distribution.',
                'hsn_code' => '2201',
                'default_price' => 180.00,
                'cost_price' => 95.00,
                'tax_rate' => 18.00,
                'current_stock' => 85.00,
                'min_stock_level' => 35.00,
                'track_stock' => true,
                'is_active' => true,
            ],
            [
                'category_id' => $finishedWater->id,
                'unit_id' => $unitMap['BOX']->id,
                'name' => '500ml Packaged Water (Case of 24)',
                'sku' => 'MA-BOT-500ML',
                'unit' => 'Box/24',
                'description' => 'Compact 500ml travel bottles packed 24 per case with tamper-evident caps.',
                'hsn_code' => '2201',
                'default_price' => 210.00,
                'cost_price' => 110.00,
                'tax_rate' => 18.00,
                'current_stock' => 45.00,
                'min_stock_level' => 30.00,
                'track_stock' => true,
                'is_active' => true,
            ],
            [
                'category_id' => $finishedWater->id,
                'unit_id' => $unitMap['BOX']->id,
                'name' => '200ml Water Cups / Mini Bottles (Case of 48)',
                'sku' => 'MA-BOT-200ML',
                'unit' => 'Box/48',
                'description' => 'Mini event water bottles suitable for corporate gatherings and catering.',
                'hsn_code' => '2201',
                'default_price' => 240.00,
                'cost_price' => 130.00,
                'tax_rate' => 18.00,
                'current_stock' => 30.00,
                'min_stock_level' => 25.00,
                'track_stock' => true,
                'is_active' => true,
            ],
            [
                'category_id' => $finishedWater->id,
                'unit_id' => $unitMap['BOX']->id,
                'name' => '100ml Water Pouches / Cups (Case of 60)',
                'sku' => 'MA-BOT-100ML',
                'unit' => 'Box/60',
                'description' => 'Single-serve 100ml water cups for quick hydration stations.',
                'hsn_code' => '2201',
                'default_price' => 150.00,
                'cost_price' => 80.00,
                'tax_rate' => 18.00,
                'current_stock' => 20.00,
                'min_stock_level' => 20.00,
                'track_stock' => true,
                'is_active' => true,
            ],
            [
                'category_id' => $packaging->id,
                'unit_id' => $unitMap['NOS']->id,
                'name' => 'PET Bottle Preforms 1 Litre',
                'sku' => 'MA-RAW-PREFORM-1L',
                'unit' => 'Nos',
                'description' => 'High quality raw PET preforms for in-house stretch blow molding.',
                'hsn_code' => '3923',
                'default_price' => 6.50,
                'cost_price' => 4.20,
                'tax_rate' => 18.00,
                'current_stock' => 5000.00,
                'min_stock_level' => 1500.00,
                'track_stock' => true,
                'is_active' => true,
            ],
            [
                'category_id' => $packaging->id,
                'unit_id' => $unitMap['KG']->id,
                'name' => 'Plastic Bottle Bottoms & Resins (HDPE/PET)',
                'sku' => 'MA-RAW-RESIN',
                'unit' => 'Kg',
                'description' => 'Virgin polymer granules for can base reinforcement and molding.',
                'hsn_code' => '3901',
                'default_price' => 140.00,
                'cost_price' => 115.00,
                'tax_rate' => 18.00,
                'current_stock' => 250.00,
                'min_stock_level' => 100.00,
                'track_stock' => true,
                'is_active' => true,
            ],
            [
                'category_id' => $packaging->id,
                'unit_id' => $unitMap['NOS']->id,
                'name' => 'Corrugated Cardboard Outer Cartons (Heavy-Duty)',
                'sku' => 'MA-RAW-CARDBOARD',
                'unit' => 'Nos',
                'description' => '5-ply printed master shipping cartons for bottle boxes.',
                'hsn_code' => '4819',
                'default_price' => 28.00,
                'cost_price' => 20.00,
                'tax_rate' => 18.00,
                'current_stock' => 400.00,
                'min_stock_level' => 150.00,
                'track_stock' => true,
                'is_active' => true,
            ],
            [
                'category_id' => $packaging->id,
                'unit_id' => $unitMap['PACK']->id,
                'name' => 'Shrink Film Plastics & Roll Packaging',
                'sku' => 'MA-RAW-PLASTICS',
                'unit' => 'Pack',
                'description' => 'LDPE heat-shrink film rolls for bundling bottle multipacks.',
                'hsn_code' => '3920',
                'default_price' => 450.00,
                'cost_price' => 340.00,
                'tax_rate' => 18.00,
                'current_stock' => 18.00,
                'min_stock_level' => 10.00,
                'track_stock' => true,
                'is_active' => true,
            ],
        ];

        foreach ($products as $p) {
            Product::updateOrCreate(
                ['sku' => $p['sku']],
                $p
            );
        }
    }
}
