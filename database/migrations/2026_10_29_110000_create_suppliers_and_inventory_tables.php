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
        // 1. Suppliers Table
        if (! Schema::hasTable('suppliers')) {
            Schema::create('suppliers', function (Blueprint $table) {
                $table->id();
                $table->string('code', 50)->unique();
                $table->string('name', 255);
                $table->string('contact_person', 255)->nullable();
                $table->string('phone', 50);
                $table->string('email', 255)->nullable();
                $table->text('address')->nullable();
                $table->string('gstin', 50)->nullable();
                $table->decimal('opening_balance', 12, 2)->default(0.00);
                $table->decimal('current_balance', 12, 2)->default(0.00);
                $table->boolean('is_active')->default(true);
                $table->text('notes')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        // 2. Enhance Purchases Table
        Schema::table('purchases', function (Blueprint $table) {
            if (! Schema::hasColumn('purchases', 'supplier_id')) {
                $table->foreignId('supplier_id')->nullable()->after('purchase_no')->constrained('suppliers')->nullOnDelete();
            }
            if (! Schema::hasColumn('purchases', 'invoice_no')) {
                $table->string('invoice_no', 100)->nullable()->after('supplier_name');
            }
            if (! Schema::hasColumn('purchases', 'subtotal')) {
                $table->decimal('subtotal', 12, 2)->default(0.00)->after('purchase_date');
            }
            if (! Schema::hasColumn('purchases', 'tax_amount')) {
                $table->decimal('tax_amount', 12, 2)->default(0.00)->after('subtotal');
            }
            if (! Schema::hasColumn('purchases', 'discount_amount')) {
                $table->decimal('discount_amount', 12, 2)->default(0.00)->after('tax_amount');
            }
            if (! Schema::hasColumn('purchases', 'paid_amount')) {
                $table->decimal('paid_amount', 12, 2)->default(0.00)->after('total_amount');
            }
            if (! Schema::hasColumn('purchases', 'payment_status')) {
                $table->string('payment_status', 50)->default('unpaid')->after('paid_amount');
            }
            if (! Schema::hasColumn('purchases', 'payment_method')) {
                $table->string('payment_method', 50)->default('cash')->after('payment_status');
            }
            if (! Schema::hasColumn('purchases', 'idempotency_key')) {
                $table->string('idempotency_key', 100)->nullable()->unique()->after('status');
            }
            if (! Schema::hasColumn('purchases', 'updated_by')) {
                $table->foreignId('updated_by')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('purchases', 'deleted_at')) {
                $table->softDeletes();
            }
        });

        // 3. Enhance Purchase Items Table
        Schema::table('purchase_items', function (Blueprint $table) {
            if (! Schema::hasColumn('purchase_items', 'product_name')) {
                $table->string('product_name', 255)->nullable()->after('product_id');
            }
            if (! Schema::hasColumn('purchase_items', 'product_sku')) {
                $table->string('product_sku', 100)->nullable()->after('product_name');
            }
            if (! Schema::hasColumn('purchase_items', 'unit')) {
                $table->string('unit', 50)->nullable()->after('quantity');
            }
            if (! Schema::hasColumn('purchase_items', 'tax_rate')) {
                $table->decimal('tax_rate', 5, 2)->default(0.00)->after('unit_cost');
            }
            if (! Schema::hasColumn('purchase_items', 'tax_amount')) {
                $table->decimal('tax_amount', 12, 2)->default(0.00)->after('tax_rate');
            }
            if (! Schema::hasColumn('purchase_items', 'discount_amount')) {
                $table->decimal('discount_amount', 12, 2)->default(0.00)->after('tax_amount');
            }
            if (! Schema::hasColumn('purchase_items', 'notes')) {
                $table->text('notes')->nullable()->after('total_cost');
            }
        });

        // 4. Inventory Movements Table (Stock Ledger)
        if (! Schema::hasTable('inventory_movements')) {
            Schema::create('inventory_movements', function (Blueprint $table) {
                $table->id();
                $table->string('movement_no', 50)->unique();
                $table->date('movement_date')->index();
                $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
                $table->string('movement_type', 50)->index(); // purchase_in, supply_out, stock_adjustment, purchase_reversal, purchase_edit, opening_stock
                $table->string('reference_type', 50)->nullable()->index(); // purchase, dealer_supply, stock_adjustment, manual
                $table->unsignedBigInteger('reference_id')->nullable()->index();
                $table->string('reference_no', 100)->nullable();
                $table->decimal('quantity', 12, 2);
                $table->decimal('signed_quantity', 12, 2);
                $table->string('unit', 50)->nullable();
                $table->decimal('unit_cost', 12, 2)->default(0.00);
                $table->decimal('total_cost', 12, 2)->default(0.00);
                $table->decimal('balance_before', 12, 2)->default(0.00);
                $table->decimal('balance_after', 12, 2)->default(0.00);
                $table->text('notes')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        // 5. Stock Adjustments Table
        if (! Schema::hasTable('stock_adjustments')) {
            Schema::create('stock_adjustments', function (Blueprint $table) {
                $table->id();
                $table->string('adjustment_no', 50)->unique();
                $table->date('adjustment_date')->index();
                $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
                $table->string('adjustment_type', 50)->index(); // addition, reduction, damage, leakage, audit_reconciliation, expiry, loss
                $table->decimal('quantity', 12, 2);
                $table->string('unit', 50)->nullable();
                $table->decimal('cost_price', 12, 2)->default(0.00);
                $table->decimal('stock_before', 12, 2)->default(0.00);
                $table->decimal('stock_after', 12, 2)->default(0.00);
                $table->string('reason', 255);
                $table->string('authorized_by', 255);
                $table->foreignId('authorized_by_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('status', 50)->default('completed');
                $table->text('notes')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->softDeletes();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_adjustments');
        Schema::dropIfExists('inventory_movements');
        Schema::dropIfExists('suppliers');
    }
};
