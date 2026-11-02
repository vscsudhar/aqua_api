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
        // 1. Product Categories
        Schema::create('product_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 2. Products
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('product_categories')->cascadeOnDelete();
            $table->string('name');
            $table->string('sku')->unique();
            $table->string('unit')->default('20L Jar');
            $table->string('hsn_code', 20)->default('2201');
            $table->decimal('default_price', 12, 2)->default(0.00);
            $table->decimal('cost_price', 12, 2)->default(0.00);
            $table->decimal('tax_rate', 5, 2)->default(18.00);
            $table->decimal('current_stock', 12, 2)->default(0.00);
            $table->decimal('min_stock_level', 12, 2)->default(10.00);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 3. Dealers
        Schema::create('dealers', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name');
            $table->string('contact_person')->nullable();
            $table->string('phone', 30);
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->string('gstin', 20)->nullable();
            $table->decimal('credit_limit', 12, 2)->default(0.00);
            $table->decimal('opening_balance', 12, 2)->default(0.00);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 4. Supplies (Water deliveries / Invoices to dealers)
        Schema::create('supplies', function (Blueprint $table) {
            $table->id();
            $table->string('supply_no', 50)->unique();
            $table->foreignId('dealer_id')->constrained('dealers')->cascadeOnDelete();
            $table->date('supply_date')->index();
            $table->decimal('subtotal', 12, 2)->default(0.00);
            $table->decimal('tax_amount', 12, 2)->default(0.00);
            $table->decimal('total_amount', 12, 2)->default(0.00);
            $table->enum('status', ['delivered', 'pending', 'cancelled'])->default('delivered')->index();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // 5. Supply Items
        Schema::create('supply_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supply_id')->constrained('supplies')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->decimal('quantity', 12, 2);
            $table->decimal('unit_price', 12, 2);
            $table->decimal('tax_rate', 5, 2)->default(18.00);
            $table->decimal('total_amount', 12, 2);
            $table->timestamps();
        });

        // 6. Payments (Collections received from dealers)
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->string('payment_no', 50)->unique();
            $table->foreignId('dealer_id')->constrained('dealers')->cascadeOnDelete();
            $table->date('payment_date')->index();
            $table->decimal('amount', 12, 2);
            $table->enum('payment_mode', ['cash', 'cheque', 'upi', 'bank_transfer', 'neft'])->default('cash');
            $table->string('reference_no')->nullable();
            $table->enum('status', ['completed', 'pending', 'bounced', 'cancelled'])->default('completed')->index();
            $table->text('remarks')->nullable();
            $table->foreignId('collected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // 7. Purchases (Inventory replenishment)
        Schema::create('purchases', function (Blueprint $table) {
            $table->id();
            $table->string('purchase_no', 50)->unique();
            $table->string('supplier_name');
            $table->date('purchase_date')->index();
            $table->decimal('total_amount', 12, 2)->default(0.00);
            $table->enum('status', ['received', 'pending', 'cancelled'])->default('received')->index();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // 8. Purchase Items
        Schema::create('purchase_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_id')->constrained('purchases')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->decimal('quantity', 12, 2);
            $table->decimal('unit_cost', 12, 2);
            $table->decimal('total_cost', 12, 2);
            $table->timestamps();
        });

        // 9. Dealer Ledger Entries (Single Source of Truth)
        Schema::create('dealer_ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dealer_id')->constrained('dealers')->cascadeOnDelete();
            $table->date('entry_date')->index();
            $table->enum('entry_type', ['opening_balance', 'supply', 'payment', 'adjustment', 'return'])->index();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('reference_no', 50)->nullable();
            $table->decimal('debit', 12, 2)->default(0.00); // Amount dealer owes (Supply/Invoice)
            $table->decimal('credit', 12, 2)->default(0.00); // Amount dealer paid (Collection)
            $table->decimal('running_balance', 12, 2)->default(0.00);
            $table->string('narration')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dealer_ledger_entries');
        Schema::dropIfExists('purchase_items');
        Schema::dropIfExists('purchases');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('supply_items');
        Schema::dropIfExists('supplies');
        Schema::dropIfExists('dealers');
        Schema::dropIfExists('products');
        Schema::dropIfExists('product_categories');
    }
};
