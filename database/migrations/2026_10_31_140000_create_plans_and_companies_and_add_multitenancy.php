<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Create plans table
        if (! Schema::hasTable('plans')) {
            Schema::create('plans', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->text('description')->nullable();
                $table->decimal('price', 10, 2)->default(0.00);
                $table->string('duration')->default('30 Days');
                $table->unsignedInteger('max_users')->default(5);
                $table->enum('status', ['active', 'inactive'])->default('active');
                $table->timestamps();
            });
        }

        // 2. Create companies table
        if (! Schema::hasTable('companies')) {
            Schema::create('companies', function (Blueprint $table) {
                $table->id();
                $table->string('company_code')->unique();
                $table->string('name');
                $table->foreignId('plan_id')->nullable()->constrained('plans')->nullOnDelete();
                $table->enum('status', ['active', 'inactive'])->default('active');
                $table->string('email')->nullable();
                $table->string('phone')->nullable();
                $table->text('address')->nullable();
                $table->timestamps();
            });
        }

        // 3. Update users table role and add company_id
        if (Schema::hasTable('users')) {
            // First modify role column to allow super_admin
            if (DB::getDriverName() === 'mysql') {
                DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('super_admin', 'admin', 'manager', 'accountant', 'sales_rep') NOT NULL DEFAULT 'sales_rep'");
            }

            Schema::table('users', function (Blueprint $table) {
                if (! Schema::hasColumn('users', 'company_id')) {
                    $table->foreignId('company_id')->nullable()->after('id')->constrained('companies')->nullOnDelete();
                }
            });
        }

        // 4. Add company_id to tenant-owned tables
        $tablesToUpdate = [
            'company_settings',
            'dealers',
            'products',
            'product_categories',
            'units',
            'tax_rates',
            'suppliers',
            'purchases',
            'purchase_items',
            'inventory_movements',
            'stock_adjustments',
            'invoices',
            'invoice_items',
            'invoice_payments',
            'credit_notes',
            'dealer_supplies',
            'dealer_supply_items',
            'dealer_payments',
            'dealer_product_prices',
            'daily_other_transactions',
            'dealer_ledger_entries',
        ];

        foreach ($tablesToUpdate as $tableName) {
            if (Schema::hasTable($tableName) && ! Schema::hasColumn($tableName, 'company_id')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->unsignedBigInteger('company_id')->nullable()->index();
                });
            }
        }

        // 5. Seed default plans
        $defaultPlans = [
            [
                'id' => 1,
                'name' => 'Starter Plan',
                'description' => 'Ideal for small water distribution businesses with up to 5 staff accounts.',
                'price' => 999.00,
                'duration' => '30 Days',
                'max_users' => 5,
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 2,
                'name' => 'Growth Business Plan',
                'description' => 'For growing packaging & distribution units with up to 15 team members.',
                'price' => 2499.00,
                'duration' => '365 Days',
                'max_users' => 15,
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 3,
                'name' => 'Enterprise Plan',
                'description' => 'Complete enterprise distribution suite with up to 50 staff users and priority support.',
                'price' => 4999.00,
                'duration' => '365 Days',
                'max_users' => 50,
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        foreach ($defaultPlans as $plan) {
            DB::table('plans')->updateOrInsert(['id' => $plan['id']], $plan);
        }

        // 6. Seed default Company 1 (Marina Aqua)
        DB::table('companies')->updateOrInsert(
            ['id' => 1],
            [
                'company_code' => 'CMP-0001',
                'name' => 'Marina Aqua Pure Water Co.',
                'plan_id' => 2,
                'status' => 'active',
                'email' => 'billing@marinaaqua.com',
                'phone' => '+91 94441 23456',
                'address' => 'Plot No. 14, OMR IT Highway, Thoraipakkam, Chennai - 600097',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        // 7. Associate existing business data with Company 1
        foreach ($tablesToUpdate as $tableName) {
            if (Schema::hasTable($tableName)) {
                DB::table($tableName)->whereNull('company_id')->update(['company_id' => 1]);
            }
        }

        // Update existing user 1 to belong to company 1 as admin
        DB::table('users')->where('id', 1)->update([
            'company_id' => 1,
            'role' => 'admin',
        ]);

        // 8. Create Super Admin User for AQUA_USER panel
        $superAdminExists = DB::table('users')->where('email', 'superadmin@aquasystem.com')->exists();
        if (! $superAdminExists) {
            DB::table('users')->insert([
                'name' => 'Super Administrator',
                'email' => 'superadmin@aquasystem.com',
                'username' => 'superadmin',
                'phone' => '+91 98765 43210',
                'password' => Hash::make('Admin@1234'),
                'role' => 'super_admin',
                'company_id' => null,
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tablesToUpdate = [
            'company_settings',
            'dealers',
            'products',
            'product_categories',
            'units',
            'tax_rates',
            'suppliers',
            'purchases',
            'purchase_items',
            'inventory_movements',
            'stock_adjustments',
            'invoices',
            'invoice_items',
            'invoice_payments',
            'credit_notes',
            'dealer_supplies',
            'dealer_supply_items',
            'dealer_payments',
            'dealer_product_prices',
            'daily_other_transactions',
            'dealer_ledger_entries',
        ];

        foreach ($tablesToUpdate as $tableName) {
            if (Schema::hasTable($tableName) && Schema::hasColumn($tableName, 'company_id')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->dropColumn('company_id');
                });
            }
        }

        if (Schema::hasTable('users') && Schema::hasColumn('users', 'company_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropForeign(['company_id']);
                $table->dropColumn('company_id');
            });
        }

        Schema::dropIfExists('companies');
        Schema::dropIfExists('plans');
    }
};
