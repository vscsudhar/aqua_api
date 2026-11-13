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
        if (Schema::hasTable('invoices')) {
            Schema::table('invoices', function (Blueprint $table) {
                if (! Schema::hasColumn('invoices', 'customer_type')) {
                    $table->string('customer_type', 20)->default('dealer')->after('invoice_type');
                    $table->index('customer_type');
                }
                if (! Schema::hasColumn('invoices', 'customer_company_id')) {
                    $table->foreignId('customer_company_id')->nullable()->after('dealer_id')->constrained('companies')->nullOnDelete();
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('invoices')) {
            Schema::table('invoices', function (Blueprint $table) {
                if (Schema::hasColumn('invoices', 'customer_company_id')) {
                    $table->dropForeign(['customer_company_id']);
                    $table->dropColumn('customer_company_id');
                }
                if (Schema::hasColumn('invoices', 'customer_type')) {
                    $table->dropIndex(['customer_type']);
                    $table->dropColumn('customer_type');
                }
            });
        }
    }
};
