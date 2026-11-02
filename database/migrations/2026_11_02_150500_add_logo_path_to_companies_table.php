<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('companies', 'logo_path')) {
            Schema::table('companies', function (Blueprint $table) {
                $table->string('logo_path', 255)->nullable()->after('name');
            });
        }

        // Sync existing company_settings logo_path to companies table
        $settings = DB::table('company_settings')->whereNotNull('logo_path')->get();
        foreach ($settings as $s) {
            if ($s->company_id) {
                DB::table('companies')->where('id', $s->company_id)->update([
                    'logo_path' => $s->logo_path,
                ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('companies', 'logo_path')) {
            Schema::table('companies', function (Blueprint $table) {
                $table->dropColumn('logo_path');
            });
        }
    }
};

