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
        Schema::table('invoices', function (Blueprint $table) {
            if (! Schema::hasColumn('invoices', 'payment_mode')) {
                $table->string('payment_mode', 50)->nullable()->after('payment_status');
            }
            if (! Schema::hasColumn('invoices', 'additional_taxes')) {
                $table->json('additional_taxes')->nullable()->after('tax_total');
            }
            if (! Schema::hasColumn('invoices', 'additional_tax_total')) {
                $table->decimal('additional_tax_total', 12, 2)->default(0.00)->after('additional_taxes');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            if (Schema::hasColumn('invoices', 'additional_tax_total')) {
                $table->dropColumn('additional_tax_total');
            }
            if (Schema::hasColumn('invoices', 'additional_taxes')) {
                $table->dropColumn('additional_taxes');
            }
            if (Schema::hasColumn('invoices', 'payment_mode')) {
                $table->dropColumn('payment_mode');
            }
        });
    }
};
