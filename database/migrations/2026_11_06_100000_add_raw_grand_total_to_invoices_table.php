<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            if (! Schema::hasColumn('invoices', 'raw_grand_total')) {
                $table->decimal('raw_grand_total', 12, 2)->nullable()->after('tax_total');
            }
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            if (Schema::hasColumn('invoices', 'raw_grand_total')) {
                $table->dropColumn('raw_grand_total');
            }
        });
    }
};
