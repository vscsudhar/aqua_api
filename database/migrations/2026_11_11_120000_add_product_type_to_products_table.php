<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('products')) {
            if (! Schema::hasColumn('products', 'product_type')) {
                Schema::table('products', function (Blueprint $table) {
                    $table->string('product_type', 20)->default('normal')->after('name');
                });
            }

            try {
                Schema::table('products', function (Blueprint $table) {
                    $table->decimal('cost_price', 12, 2)->nullable()->default(0.00)->change();
                    $table->decimal('current_stock', 12, 2)->nullable()->default(0.00)->change();
                    $table->decimal('min_stock_level', 12, 2)->nullable()->default(10.00)->change();
                });
            } catch (\Throwable $e) {
                // Fallback for MySQL environments
                try {
                    DB::statement('ALTER TABLE products MODIFY cost_price DECIMAL(12,2) NULL DEFAULT 0.00');
                    DB::statement('ALTER TABLE products MODIFY current_stock DECIMAL(12,2) NULL DEFAULT 0.00');
                    DB::statement('ALTER TABLE products MODIFY min_stock_level DECIMAL(12,2) NULL DEFAULT 10.00');
                } catch (\Throwable $ex) {
                    // Suppress if already updated or unsupported
                }
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('products') && Schema::hasColumn('products', 'product_type')) {
            Schema::table('products', function (Blueprint $table) {
                $table->dropColumn('product_type');
            });
        }
    }
};
