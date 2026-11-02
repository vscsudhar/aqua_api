<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dealers', function (Blueprint $table) {
            if (! Schema::hasColumn('dealers', 'opening_date')) {
                $table->date('opening_date')->nullable()->after('opening_balance');
            }
            if (! Schema::hasColumn('dealers', 'notes')) {
                $table->text('notes')->nullable()->after('opening_date');
            }
            if (! Schema::hasColumn('dealers', 'deleted_at')) {
                $table->softDeletes();
            }
        });

        if (! Schema::hasTable('dealer_product_prices')) {
            Schema::create('dealer_product_prices', function (Blueprint $table) {
                $table->id();
                $table->foreignId('dealer_id')->constrained('dealers')->cascadeOnDelete();
                $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
                $table->decimal('custom_price', 12, 2);
                $table->date('effective_from')->index();
                $table->date('effective_to')->nullable()->index();
                $table->boolean('is_active')->default(true)->index();
                $table->string('notes')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['dealer_id', 'product_id', 'is_active']);
                $table->index(['dealer_id', 'product_id', 'effective_from']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('dealer_product_prices');

        Schema::table('dealers', function (Blueprint $table) {
            if (Schema::hasColumn('dealers', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
            if (Schema::hasColumn('dealers', 'notes')) {
                $table->dropColumn('notes');
            }
            if (Schema::hasColumn('dealers', 'opening_date')) {
                $table->dropColumn('opening_date');
            }
        });
    }
};
