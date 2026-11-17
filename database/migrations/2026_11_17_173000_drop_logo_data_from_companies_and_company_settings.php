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
        if (Schema::hasTable('company_settings') && Schema::hasColumn('company_settings', 'logo_data')) {
            Schema::table('company_settings', function (Blueprint $table) {
                $table->dropColumn('logo_data');
            });
        }

        if (Schema::hasTable('companies') && Schema::hasColumn('companies', 'logo_data')) {
            Schema::table('companies', function (Blueprint $table) {
                $table->dropColumn('logo_data');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('company_settings') && ! Schema::hasColumn('company_settings', 'logo_data')) {
            Schema::table('company_settings', function (Blueprint $table) {
                $table->longText('logo_data')->nullable()->after('logo_path');
            });
        }

        if (Schema::hasTable('companies') && ! Schema::hasColumn('companies', 'logo_data')) {
            Schema::table('companies', function (Blueprint $table) {
                $table->longText('logo_data')->nullable()->after('logo_path');
            });
        }
    }
};
