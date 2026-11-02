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
        // 1. Create Units table
        Schema::create('units', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 20)->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 2. Enhance Products table
        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('unit_id')->nullable()->after('category_id')->constrained('units')->nullOnDelete();
            $table->text('description')->nullable()->after('name');
            $table->boolean('track_stock')->default(true)->after('current_stock');
            $table->softDeletes()->after('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['unit_id']);
            $table->dropColumn(['unit_id', 'description', 'track_stock', 'deleted_at']);
        });

        Schema::dropIfExists('units');
    }
};
