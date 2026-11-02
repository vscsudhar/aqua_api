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
        // 1. Create Tax Rates Table
        if (! Schema::hasTable('tax_rates')) {
            Schema::create('tax_rates', function (Blueprint $table) {
                $table->id();
                $table->string('name', 100);
                $table->decimal('rate', 5, 2)->default(0.00);
                $table->string('tax_type', 50)->default('gst'); // gst, vat, custom, exempt
                $table->string('applicability', 50)->default('all'); // all, goods, services
                $table->date('effective_from')->nullable();
                $table->date('effective_to')->nullable();
                $table->boolean('is_active')->default(true)->index();
                $table->boolean('is_default')->default(false);
                $table->text('description')->nullable();
                $table->timestamps();
            });
        }

        // 2. Add extra settings columns to Company Settings
        if (Schema::hasTable('company_settings')) {
            Schema::table('company_settings', function (Blueprint $table) {
                if (! Schema::hasColumn('company_settings', 'currency_symbol')) {
                    $table->string('currency_symbol', 10)->default('₹')->after('upi_id');
                }
                if (! Schema::hasColumn('company_settings', 'currency_code')) {
                    $table->string('currency_code', 10)->default('INR')->after('currency_symbol');
                }
                if (! Schema::hasColumn('company_settings', 'default_tax_rate')) {
                    $table->decimal('default_tax_rate', 5, 2)->default(18.00)->after('currency_code');
                }
                if (! Schema::hasColumn('company_settings', 'invoice_number_padding')) {
                    $table->unsignedInteger('invoice_number_padding')->default(4)->after('invoice_prefix');
                }
                if (! Schema::hasColumn('company_settings', 'next_invoice_number')) {
                    $table->unsignedInteger('next_invoice_number')->default(1)->after('invoice_number_padding');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tax_rates');

        if (Schema::hasTable('company_settings')) {
            Schema::table('company_settings', function (Blueprint $table) {
                $cols = ['currency_symbol', 'currency_code', 'default_tax_rate', 'invoice_number_padding', 'next_invoice_number'];
                foreach ($cols as $col) {
                    if (Schema::hasColumn('company_settings', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};
