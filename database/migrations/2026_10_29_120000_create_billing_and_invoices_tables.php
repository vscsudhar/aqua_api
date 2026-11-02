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
        // 1. Company Settings Table
        if (! Schema::hasTable('company_settings')) {
            Schema::create('company_settings', function (Blueprint $table) {
                $table->id();
                $table->string('company_name', 255)->default('Marina Aqua Pure Water Co.');
                $table->string('tagline', 255)->default('Pure & Mineral Rich Packaged Drinking Water');
                $table->string('phone', 50)->default('+91 94441 23456');
                $table->string('email', 255)->default('billing@marinaaqua.com');
                $table->string('website', 255)->default('www.marinaaqua.com');
                $table->text('address')->nullable();
                $table->string('state_name', 100)->default('Tamil Nadu');
                $table->string('state_code', 10)->default('33');
                $table->string('gstin', 50)->default('33AABCM1234F1Z9');
                $table->string('pan', 50)->default('AABCM1234F');
                $table->string('bank_name', 255)->default('State Bank of India');
                $table->string('bank_branch', 255)->default('Thoraipakkam, Chennai');
                $table->string('account_number', 100)->default('38992010045');
                $table->string('ifsc_code', 50)->default('SBIN0004567');
                $table->string('upi_id', 100)->default('marinaaqua@sbi');
                $table->string('logo_path', 255)->nullable();
                $table->string('invoice_prefix', 20)->default('INV');
                $table->text('invoice_footer_notes')->nullable();
                $table->timestamps();
            });
        }

        // 2. Invoices Table
        if (! Schema::hasTable('invoices')) {
            Schema::create('invoices', function (Blueprint $table) {
                $table->id();
                $table->string('invoice_no', 50)->unique();
                $table->date('invoice_date')->index();
                $table->date('due_date')->nullable();
                $table->foreignId('dealer_id')->nullable()->constrained('dealers')->nullOnDelete();
                $table->string('customer_name', 255);
                $table->string('customer_phone', 50)->nullable();
                $table->string('customer_email', 255)->nullable();
                $table->text('customer_address')->nullable();
                $table->string('customer_gstin', 50)->nullable();
                $table->string('place_of_supply', 100)->default('33-Tamil Nadu');
                $table->boolean('is_interstate')->default(false);
                $table->foreignId('dealer_supply_id')->nullable()->constrained('dealer_supplies')->nullOnDelete();
                $table->decimal('subtotal', 12, 2)->default(0.00);
                $table->decimal('discount_total', 12, 2)->default(0.00);
                $table->decimal('taxable_amount', 12, 2)->default(0.00);
                $table->decimal('cgst_amount', 12, 2)->default(0.00);
                $table->decimal('sgst_amount', 12, 2)->default(0.00);
                $table->decimal('igst_amount', 12, 2)->default(0.00);
                $table->decimal('tax_total', 12, 2)->default(0.00);
                $table->decimal('round_off', 12, 2)->default(0.00);
                $table->decimal('grand_total', 12, 2)->default(0.00);
                $table->decimal('paid_amount', 12, 2)->default(0.00);
                $table->decimal('balance_due', 12, 2)->default(0.00);
                $table->string('status', 50)->default('draft')->index(); // draft, finalized, cancelled
                $table->string('payment_status', 50)->default('unpaid')->index(); // unpaid, partially_paid, paid
                $table->text('cancellation_reason')->nullable();
                $table->timestamp('cancelled_at')->nullable();
                $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('finalized_at')->nullable();
                $table->foreignId('finalized_by')->nullable()->constrained('users')->nullOnDelete();
                $table->text('notes')->nullable();
                $table->text('terms')->nullable();
                $table->string('idempotency_key', 100)->nullable()->unique();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        // 3. Invoice Items Table
        if (! Schema::hasTable('invoice_items')) {
            Schema::create('invoice_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
                $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
                $table->string('item_description', 255);
                $table->string('hsn_sac', 50)->default('2201');
                $table->decimal('quantity', 12, 2);
                $table->string('unit', 50)->default('Unit');
                $table->decimal('unit_price', 12, 2);
                $table->decimal('discount_amount', 12, 2)->default(0.00);
                $table->decimal('taxable_amount', 12, 2)->default(0.00);
                $table->decimal('tax_rate', 5, 2)->default(0.00);
                $table->decimal('cgst_rate', 5, 2)->default(0.00);
                $table->decimal('cgst_amount', 12, 2)->default(0.00);
                $table->decimal('sgst_rate', 5, 2)->default(0.00);
                $table->decimal('sgst_amount', 12, 2)->default(0.00);
                $table->decimal('igst_rate', 5, 2)->default(0.00);
                $table->decimal('igst_amount', 12, 2)->default(0.00);
                $table->decimal('line_total', 12, 2)->default(0.00);
                $table->string('notes', 255)->nullable();
                $table->timestamps();
            });
        }

        // 4. Invoice Payments Table
        if (! Schema::hasTable('invoice_payments')) {
            Schema::create('invoice_payments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
                $table->string('payment_no', 50)->unique();
                $table->date('payment_date')->index();
                $table->decimal('amount', 12, 2);
                $table->string('payment_method', 50)->default('cash');
                $table->string('reference_no', 100)->nullable();
                $table->text('notes')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        // 5. Credit Notes Table
        if (! Schema::hasTable('credit_notes')) {
            Schema::create('credit_notes', function (Blueprint $table) {
                $table->id();
                $table->string('credit_note_no', 50)->unique();
                $table->date('credit_note_date')->index();
                $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
                $table->foreignId('dealer_id')->nullable()->constrained('dealers')->nullOnDelete();
                $table->decimal('subtotal', 12, 2)->default(0.00);
                $table->decimal('tax_amount', 12, 2)->default(0.00);
                $table->decimal('total_amount', 12, 2)->default(0.00);
                $table->string('reason', 255);
                $table->text('notes')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('credit_notes');
        Schema::dropIfExists('invoice_payments');
        Schema::dropIfExists('invoice_items');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('company_settings');
    }
};
