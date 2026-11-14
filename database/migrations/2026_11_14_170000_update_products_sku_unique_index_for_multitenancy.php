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
        Schema::table('products', function (Blueprint $table) {
            try {
                $table->dropUnique('products_sku_unique');
            } catch (\Throwable $e) {
                // Ignore if already dropped
            }

            try {
                $table->unique(['company_id', 'sku'], 'products_company_id_sku_unique');
            } catch (\Throwable $e) {
                // Ignore if already created
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            try {
                $table->dropUnique('products_company_id_sku_unique');
            } catch (\Throwable $e) {
                // Ignore
            }

            try {
                $table->unique('sku', 'products_sku_unique');
            } catch (\Throwable $e) {
                // Ignore
            }
        });
    }
};
