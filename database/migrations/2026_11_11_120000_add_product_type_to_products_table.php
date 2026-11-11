<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (! Schema::hasColumn('products', 'product_type')) {
                $table->string('product_type', 20)->default('normal')->after('name');
            }
            $table->decimal('cost_price', 12, 2)->nullable()->default(0.00)->change();
            $table->decimal('current_stock', 12, 2)->nullable()->default(0.00)->change();
            $table->decimal('min_stock_level', 12, 2)->nullable()->default(10.00)->change();
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (Schema::hasColumn('products', 'product_type')) {
                $table->dropColumn('product_type');
            }
        });
    }
};
