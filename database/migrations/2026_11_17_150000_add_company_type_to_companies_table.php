<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            if (! Schema::hasColumn('companies', 'company_type')) {
                $table->string('company_type', 30)->default('main')->after('owner_company_id')->index();
            }
        });

        // 1. Identify and classify historical dealer / corporate customer companies:
        // Records that have an owner_company_id or lack a subscription plan_id are dealer companies.
        DB::table('companies')
            ->whereNotNull('owner_company_id')
            ->orWhereNull('plan_id')
            ->update([
                'company_type' => 'dealer',
            ]);

        // 2. Identify and classify main subscription companies:
        // Records with no owner_company_id and a valid plan_id are main subscription companies.
        DB::table('companies')
            ->whereNull('owner_company_id')
            ->whereNotNull('plan_id')
            ->update([
                'company_type' => 'main',
            ]);

        // 3. Resolve any unassigned dealer companies created during testing:
        // If a dealer company has owner_company_id as NULL, assign it to the primary root tenant (id: 1)
        // so it remains properly isolated under Marina Aqua rather than orphaned.
        DB::table('companies')
            ->where('company_type', 'dealer')
            ->whereNull('owner_company_id')
            ->update([
                'owner_company_id' => 1,
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            if (Schema::hasColumn('companies', 'company_type')) {
                $table->dropColumn('company_type');
            }
        });
    }
};
