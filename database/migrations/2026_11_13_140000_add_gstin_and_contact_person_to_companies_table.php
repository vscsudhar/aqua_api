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
        if (Schema::hasTable('companies')) {
            Schema::table('companies', function (Blueprint $table) {
                if (! Schema::hasColumn('companies', 'gstin')) {
                    $table->string('gstin', 50)->nullable()->after('address');
                }
                if (! Schema::hasColumn('companies', 'contact_person')) {
                    $table->string('contact_person', 100)->nullable()->after('name');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('companies')) {
            Schema::table('companies', function (Blueprint $table) {
                if (Schema::hasColumn('companies', 'gstin')) {
                    $table->dropColumn('gstin');
                }
                if (Schema::hasColumn('companies', 'contact_person')) {
                    $table->dropColumn('contact_person');
                }
            });
        }
    }
};
