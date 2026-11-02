<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Scoped unique constraint on units (company_id, code)
        Schema::table('units', function (Blueprint $table) {
            $table->dropUnique('units_code_unique');
            $table->unique(['company_id', 'code'], 'units_company_id_code_unique');
        });

        // 2. Scoped unique constraint on product_categories (company_id, code)
        Schema::table('product_categories', function (Blueprint $table) {
            $table->dropUnique('product_categories_code_unique');
            $table->unique(['company_id', 'code'], 'product_categories_company_id_code_unique');
        });

        // 3. Seed standard units for existing companies that have no units
        $companies = DB::table('companies')->get();
        $defaultUnits = [
            ['name' => 'Litre', 'code' => 'LTR', 'description' => 'Volume in litres for bottled water and 20L cans'],
            ['name' => 'Millilitre', 'code' => 'ML', 'description' => 'Volume in millilitres (200ml, 500ml)'],
            ['name' => 'Numbers', 'code' => 'NOS', 'description' => 'Individual piece count or count of cans'],
            ['name' => 'Kilogram', 'code' => 'KG', 'description' => 'Mass in kilograms for bulk plastics, resin, scrap'],
            ['name' => 'Pack', 'code' => 'PACK', 'description' => 'Shrink-wrapped bottle package (e.g. 12 pack, 24 pack)'],
            ['name' => 'Can', 'code' => 'CAN', 'description' => '20L bubble water top cans'],
            ['name' => 'Box', 'code' => 'BOX', 'description' => 'Corrugated boxes for bottled water shipping'],
        ];

        foreach ($companies as $company) {
            $existingCount = DB::table('units')->where('company_id', $company->id)->count();
            if ($existingCount === 0) {
                $now = now();
                $rows = array_map(function ($u) use ($company, $now) {
                    return [
                        'company_id' => $company->id,
                        'name' => $u['name'],
                        'code' => $u['code'],
                        'description' => $u['description'],
                        'is_active' => true,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }, $defaultUnits);
                DB::table('units')->insert($rows);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('units', function (Blueprint $table) {
            $table->dropUnique('units_company_id_code_unique');
            $table->unique('code', 'units_code_unique');
        });

        Schema::table('product_categories', function (Blueprint $table) {
            $table->dropUnique('product_categories_company_id_code_unique');
            $table->unique('code', 'product_categories_code_unique');
        });
    }
};

