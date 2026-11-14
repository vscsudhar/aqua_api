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
        // Align dealer_supplies and dealer_payments uniqueness scope with company-scoped multi-tenancy,
        // mirroring the exact pattern used in 2026_11_02_133000_update_billing_unique_indices_for_multitenancy.php
        Schema::table('dealer_supplies', function (Blueprint $table) {
            try {
                $table->dropUnique('supplies_supply_no_unique');
            } catch (\Throwable $e) {
                try {
                    $table->dropUnique('dealer_supplies_supply_no_unique');
                } catch (\Throwable $e2) {
                    // Index may already be dropped or named differently
                }
            }

            try {
                $table->unique(['company_id', 'supply_no'], 'dealer_supplies_company_id_supply_no_unique');
            } catch (\Throwable $e) {
                // Index may already exist
            }
        });

        Schema::table('dealer_payments', function (Blueprint $table) {
            try {
                $table->dropUnique('payments_payment_no_unique');
            } catch (\Throwable $e) {
                try {
                    $table->dropUnique('dealer_payments_payment_no_unique');
                } catch (\Throwable $e2) {
                    // Index may already be dropped or named differently
                }
            }

            try {
                $table->unique(['company_id', 'payment_no'], 'dealer_payments_company_id_payment_no_unique');
            } catch (\Throwable $e) {
                // Index may already exist
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('dealer_supplies', function (Blueprint $table) {
            try {
                $table->dropUnique('dealer_supplies_company_id_supply_no_unique');
            } catch (\Throwable $e) {}
            try {
                $table->unique('supply_no', 'supplies_supply_no_unique');
            } catch (\Throwable $e) {}
        });

        Schema::table('dealer_payments', function (Blueprint $table) {
            try {
                $table->dropUnique('dealer_payments_company_id_payment_no_unique');
            } catch (\Throwable $e) {}
            try {
                $table->unique('payment_no', 'payments_payment_no_unique');
            } catch (\Throwable $e) {}
        });
    }
};
