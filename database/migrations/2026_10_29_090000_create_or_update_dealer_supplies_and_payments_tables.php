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
        // 1. Rename existing tables if present, otherwise create fresh
        if (Schema::hasTable('supplies') && ! Schema::hasTable('dealer_supplies')) {
            Schema::rename('supplies', 'dealer_supplies');
        }

        if (Schema::hasTable('supply_items') && ! Schema::hasTable('dealer_supply_items')) {
            Schema::rename('supply_items', 'dealer_supply_items');
        }

        if (Schema::hasTable('payments') && ! Schema::hasTable('dealer_payments')) {
            Schema::rename('payments', 'dealer_payments');
        }

        // 2. Ensure dealer_supplies table exists and has all required fields
        if (! Schema::hasTable('dealer_supplies')) {
            Schema::create('dealer_supplies', function (Blueprint $table) {
                $table->id();
                $table->string('supply_no', 50)->unique();
                $table->string('idempotency_key', 100)->nullable()->unique();
                $table->foreignId('dealer_id')->constrained('dealers')->cascadeOnDelete();
                $table->date('supply_date')->index();
                $table->decimal('subtotal', 12, 2)->default(0.00);
                $table->decimal('tax_amount', 12, 2)->default(0.00);
                $table->decimal('discount_amount', 12, 2)->default(0.00);
                $table->decimal('adjustment_amount', 12, 2)->default(0.00);
                $table->decimal('total_amount', 12, 2)->default(0.00);
                $table->decimal('paid_amount', 12, 2)->default(0.00);
                $table->decimal('outstanding_amount', 12, 2)->default(0.00);
                $table->enum('payment_status', ['unpaid', 'partially_paid', 'paid'])->default('unpaid')->index();
                $table->enum('status', ['delivered', 'pending', 'cancelled'])->default('delivered')->index();
                $table->text('notes')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->softDeletes();
            });
        } else {
            Schema::table('dealer_supplies', function (Blueprint $table) {
                if (! Schema::hasColumn('dealer_supplies', 'idempotency_key')) {
                    $table->string('idempotency_key', 100)->nullable()->unique()->after('supply_no');
                }
                if (! Schema::hasColumn('dealer_supplies', 'discount_amount')) {
                    $table->decimal('discount_amount', 12, 2)->default(0.00)->after('tax_amount');
                }
                if (! Schema::hasColumn('dealer_supplies', 'adjustment_amount')) {
                    $table->decimal('adjustment_amount', 12, 2)->default(0.00)->after('discount_amount');
                }
                if (! Schema::hasColumn('dealer_supplies', 'paid_amount')) {
                    $table->decimal('paid_amount', 12, 2)->default(0.00)->after('total_amount');
                }
                if (! Schema::hasColumn('dealer_supplies', 'outstanding_amount')) {
                    $table->decimal('outstanding_amount', 12, 2)->default(0.00)->after('paid_amount');
                }
                if (! Schema::hasColumn('dealer_supplies', 'payment_status')) {
                    $table->enum('payment_status', ['unpaid', 'partially_paid', 'paid'])->default('unpaid')->index()->after('outstanding_amount');
                }
                if (! Schema::hasColumn('dealer_supplies', 'updated_by')) {
                    $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete()->after('created_by');
                }
                if (! Schema::hasColumn('dealer_supplies', 'deleted_at')) {
                    $table->softDeletes();
                }
            });
        }

        // 3. Ensure dealer_supply_items table exists and has all required fields
        if (! Schema::hasTable('dealer_supply_items')) {
            Schema::create('dealer_supply_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('dealer_supply_id')->constrained('dealer_supplies')->cascadeOnDelete();
                $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
                $table->decimal('quantity', 12, 2);
                $table->string('unit', 50)->nullable();
                $table->decimal('catalog_price', 12, 2)->default(0.00);
                $table->decimal('applied_price', 12, 2)->default(0.00);
                $table->decimal('unit_price', 12, 2)->default(0.00);
                $table->decimal('tax_rate', 5, 2)->default(18.00);
                $table->decimal('tax_amount', 12, 2)->default(0.00);
                $table->decimal('line_total', 12, 2)->default(0.00);
                $table->decimal('total_amount', 12, 2)->default(0.00);
                $table->string('notes', 255)->nullable();
                $table->timestamps();
            });
        } else {
            Schema::table('dealer_supply_items', function (Blueprint $table) {
                if (! Schema::hasColumn('dealer_supply_items', 'dealer_supply_id')) {
                    $table->unsignedBigInteger('dealer_supply_id')->nullable()->after('id');
                }
                if (! Schema::hasColumn('dealer_supply_items', 'unit')) {
                    $table->string('unit', 50)->nullable()->after('product_id');
                }
                if (! Schema::hasColumn('dealer_supply_items', 'catalog_price')) {
                    $table->decimal('catalog_price', 12, 2)->default(0.00)->after('quantity');
                }
                if (! Schema::hasColumn('dealer_supply_items', 'applied_price')) {
                    $table->decimal('applied_price', 12, 2)->default(0.00)->after('catalog_price');
                }
                if (! Schema::hasColumn('dealer_supply_items', 'tax_amount')) {
                    $table->decimal('tax_amount', 12, 2)->default(0.00)->after('tax_rate');
                }
                if (! Schema::hasColumn('dealer_supply_items', 'line_total')) {
                    $table->decimal('line_total', 12, 2)->default(0.00)->after('total_amount');
                }
                if (! Schema::hasColumn('dealer_supply_items', 'notes')) {
                    $table->string('notes', 255)->nullable()->after('line_total');
                }
            });
        }

        // 4. Ensure dealer_payments table exists and has all required fields
        if (! Schema::hasTable('dealer_payments')) {
            Schema::create('dealer_payments', function (Blueprint $table) {
                $table->id();
                $table->string('payment_no', 50)->unique();
                $table->string('idempotency_key', 100)->nullable()->unique();
                $table->foreignId('dealer_id')->constrained('dealers')->cascadeOnDelete();
                $table->foreignId('dealer_supply_id')->nullable()->constrained('dealer_supplies')->nullOnDelete();
                $table->date('payment_date')->index();
                $table->decimal('amount', 12, 2);
                $table->string('payment_method', 50)->default('cash')->index();
                $table->string('payment_mode', 50)->nullable();
                $table->string('reference_no')->nullable();
                $table->enum('status', ['completed', 'pending', 'bounced', 'cancelled'])->default('completed')->index();
                $table->text('remarks')->nullable();
                $table->text('notes')->nullable();
                $table->foreignId('collected_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->softDeletes();
            });
        } else {
            Schema::table('dealer_payments', function (Blueprint $table) {
                if (! Schema::hasColumn('dealer_payments', 'idempotency_key')) {
                    $table->string('idempotency_key', 100)->nullable()->unique()->after('payment_no');
                }
                if (! Schema::hasColumn('dealer_payments', 'dealer_supply_id')) {
                    $table->foreignId('dealer_supply_id')->nullable()->constrained('dealer_supplies')->nullOnDelete()->after('dealer_id');
                }
                if (! Schema::hasColumn('dealer_payments', 'payment_method')) {
                    $table->string('payment_method', 50)->default('cash')->index()->after('amount');
                }
                if (! Schema::hasColumn('dealer_payments', 'notes')) {
                    $table->text('notes')->nullable()->after('remarks');
                }
                if (! Schema::hasColumn('dealer_payments', 'created_by')) {
                    $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete()->after('collected_by');
                }
                if (! Schema::hasColumn('dealer_payments', 'updated_by')) {
                    $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete()->after('created_by');
                }
                if (! Schema::hasColumn('dealer_payments', 'deleted_at')) {
                    $table->softDeletes();
                }
            });
        }

        // 5. Backfill column values if migrating from existing data
        if (Schema::hasColumn('dealer_supply_items', 'supply_id') && Schema::hasColumn('dealer_supply_items', 'dealer_supply_id')) {
            DB::statement('UPDATE dealer_supply_items SET dealer_supply_id = supply_id WHERE dealer_supply_id IS NULL');
        }
        if (Schema::hasColumn('dealer_supply_items', 'unit_price') && Schema::hasColumn('dealer_supply_items', 'applied_price')) {
            DB::statement('UPDATE dealer_supply_items SET applied_price = unit_price WHERE applied_price = 0');
        }
        if (Schema::hasColumn('dealer_supply_items', 'total_amount') && Schema::hasColumn('dealer_supply_items', 'line_total')) {
            DB::statement('UPDATE dealer_supply_items SET line_total = total_amount WHERE line_total = 0');
        }
        if (Schema::hasColumn('dealer_supplies', 'total_amount') && Schema::hasColumn('dealer_supplies', 'outstanding_amount')) {
            DB::statement('UPDATE dealer_supplies SET outstanding_amount = total_amount WHERE outstanding_amount = 0 AND paid_amount = 0');
        }
        if (Schema::hasColumn('dealer_payments', 'payment_mode') && Schema::hasColumn('dealer_payments', 'payment_method')) {
            DB::statement("UPDATE dealer_payments SET payment_method = payment_mode WHERE payment_method = 'cash' AND payment_mode IS NOT NULL");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Safe reversible migration
    }
};
