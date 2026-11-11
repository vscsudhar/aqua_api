<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('company_settings')) {
            try {
                Schema::table('company_settings', function (Blueprint $table) {
                    $table->string('upi_id', 100)->nullable()->default(null)->change();
                    $table->string('bank_name', 255)->nullable()->default(null)->change();
                    $table->string('bank_branch', 255)->nullable()->default(null)->change();
                    $table->string('account_number', 100)->nullable()->default(null)->change();
                    $table->string('ifsc_code', 50)->nullable()->default(null)->change();
                });
            } catch (\Throwable $e) {
                try {
                    DB::statement('ALTER TABLE company_settings MODIFY upi_id VARCHAR(100) NULL DEFAULT NULL');
                    DB::statement('ALTER TABLE company_settings MODIFY bank_name VARCHAR(255) NULL DEFAULT NULL');
                    DB::statement('ALTER TABLE company_settings MODIFY bank_branch VARCHAR(255) NULL DEFAULT NULL');
                    DB::statement('ALTER TABLE company_settings MODIFY account_number VARCHAR(100) NULL DEFAULT NULL');
                    DB::statement('ALTER TABLE company_settings MODIFY ifsc_code VARCHAR(50) NULL DEFAULT NULL');
                } catch (\Throwable $ex) {
                    // Suppress
                }
            }
        }
    }

    public function down(): void
    {
        //
    }
};
