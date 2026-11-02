<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('companies', 'expires_at')) {
            Schema::table('companies', function (Blueprint $table) {
                $table->date('expires_at')->nullable()->after('plan_id');
            });
        }

        // Backfill existing companies with expires_at based on plan duration
        $companies = DB::table('companies')->get();
        foreach ($companies as $c) {
            $plan = $c->plan_id ? DB::table('plans')->where('id', $c->plan_id)->first() : null;
            $duration = $plan?->duration ?? '30 Days';
            $days = 30;
            if (preg_match('/(\d+)\s*day/i', $duration, $m)) {
                $days = (int) $m[1];
            } elseif (preg_match('/(\d+)\s*year/i', $duration, $m)) {
                $days = (int) $m[1] * 365;
            } elseif (preg_match('/(\d+)\s*month/i', $duration, $m)) {
                $days = (int) $m[1] * 30;
            }
            $createdAt = $c->created_at ? Carbon::parse($c->created_at) : Carbon::now();
            $expiresAt = $createdAt->copy()->addDays($days)->toDateString();

            DB::table('companies')->where('id', $c->id)->update([
                'expires_at' => $expiresAt,
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('companies', 'expires_at')) {
            Schema::table('companies', function (Blueprint $table) {
                $table->dropColumn('expires_at');
            });
        }
    }
};

