<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Add inventory_start_date to products table if not present
        if (Schema::hasTable('products') && ! Schema::hasColumn('products', 'inventory_start_date')) {
            Schema::table('products', function (Blueprint $table) {
                $table->date('inventory_start_date')->nullable()->after('track_stock');
            });
        }

        // 2. Create public_parties table
        if (! Schema::hasTable('public_parties')) {
            Schema::create('public_parties', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
                $table->string('party_name');
                $table->string('contact_number', 30)->nullable();
                $table->text('address')->nullable();
                $table->string('status', 20)->default('active')->index();
                $table->timestamps();

                $table->index(['company_id', 'status']);
                $table->index(['company_id', 'party_name']);
            });
        }

        // 3. Create public_sales table
        if (! Schema::hasTable('public_sales')) {
            Schema::create('public_sales', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
                $table->foreignId('party_id')->constrained('public_parties')->restrictOnDelete();
                $table->date('sale_date')->index();
                $table->string('sale_time', 20)->nullable();
                $table->decimal('cash_amount', 12, 2)->default(0.00);
                $table->decimal('upi_amount', 12, 2)->default(0.00);
                $table->decimal('total_amount', 12, 2)->default(0.00);
                $table->text('notes')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['company_id', 'sale_date']);
                $table->index(['company_id', 'party_id', 'sale_date']);
            });
        }

        // 4. Create public_sale_items table
        if (! Schema::hasTable('public_sale_items')) {
            Schema::create('public_sale_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('public_sale_id')->constrained('public_sales')->cascadeOnDelete();
                $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
                $table->string('product_name_snapshot');
                $table->string('unit_snapshot')->nullable();
                $table->decimal('rate', 12, 2)->default(0.00);
                $table->decimal('quantity', 12, 2)->default(1.00);
                $table->decimal('amount', 12, 2)->default(0.00);
                $table->decimal('stock_deducted', 12, 2)->default(0.00);
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index(['public_sale_id', 'product_id']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('public_sale_items');
        Schema::dropIfExists('public_sales');
        Schema::dropIfExists('public_parties');

        if (Schema::hasTable('products') && Schema::hasColumn('products', 'inventory_start_date')) {
            Schema::table('products', function (Blueprint $table) {
                $table->dropColumn('inventory_start_date');
            });
        }
    }
};
