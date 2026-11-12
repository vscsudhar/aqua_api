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
        if (Schema::hasTable('invoices') && ! Schema::hasColumn('invoices', 'invoice_type')) {
            Schema::table('invoices', function (Blueprint $table) {
                $table->string('invoice_type', 30)->default('with_gst')->after('is_interstate');
                $table->index('invoice_type');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('invoices') && Schema::hasColumn('invoices', 'invoice_type')) {
            Schema::table('invoices', function (Blueprint $table) {
                $table->dropIndex(['invoice_type']);
                $table->dropColumn('invoice_type');
            });
        }
    }
};
