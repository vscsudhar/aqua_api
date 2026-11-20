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
        if (Schema::hasTable('company_settings') && Schema::hasColumn('company_settings', 'phone')) {
            Schema::table('company_settings', function (Blueprint $table) {
                $table->string('phone', 50)->nullable()->default(null)->change();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('company_settings') && Schema::hasColumn('company_settings', 'phone')) {
            Schema::table('company_settings', function (Blueprint $table) {
                $table->string('phone', 50)->nullable(false)->default('')->change();
            });
        }
    }
};
