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
        if (Schema::hasTable('dealer_payments')) {
            Schema::table('dealer_payments', function (Blueprint $table) {
                if (! Schema::hasColumn('dealer_payments', 'include_in_ledger_amount')) {
                    $table->boolean('include_in_ledger_amount')->default(true)->after('status');
                }
            });
        }

        if (Schema::hasTable('dealer_supply_items')) {
            Schema::table('dealer_supply_items', function (Blueprint $table) {
                if (! Schema::hasColumn('dealer_supply_items', 'supply_id')) {
                    $table->unsignedBigInteger('supply_id')->nullable()->after('dealer_supply_id');
                }
            });
        }

        if (Schema::hasTable('dealer_supplies')) {
            Schema::table('dealer_supplies', function (Blueprint $table) {
                if (! Schema::hasColumn('dealer_supplies', 'paid_amount')) {
                    $table->decimal('paid_amount', 12, 2)->default(0.00)->after('total_amount');
                }
                if (! Schema::hasColumn('dealer_supplies', 'outstanding_amount')) {
                    $table->decimal('outstanding_amount', 12, 2)->default(0.00)->after('paid_amount');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('dealer_payments')) {
            Schema::table('dealer_payments', function (Blueprint $table) {
                if (Schema::hasColumn('dealer_payments', 'include_in_ledger_amount')) {
                    $table->dropColumn('include_in_ledger_amount');
                }
            });
        }

        if (Schema::hasTable('dealer_supply_items')) {
            Schema::table('dealer_supply_items', function (Blueprint $table) {
                if (Schema::hasColumn('dealer_supply_items', 'supply_id')) {
                    $table->dropColumn('supply_id');
                }
            });
        }
    }
};
