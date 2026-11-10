<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('damaged_products')) {
            Schema::create('damaged_products', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->nullable()->index();
                $table->string('damage_no', 50)->unique();
                $table->date('damage_date')->index();
                $table->unsignedBigInteger('product_id')->index();
                $table->string('product_name');
                $table->string('variant')->nullable();
                $table->decimal('quantity', 12, 2);
                $table->string('unit', 50)->nullable();
                $table->decimal('unit_cost', 12, 2)->default(0.00);
                $table->decimal('total_cost', 12, 2)->default(0.00);
                $table->decimal('stock_before', 12, 2)->default(0.00);
                $table->decimal('stock_after', 12, 2)->default(0.00);
                $table->string('reason');
                $table->text('remarks')->nullable();
                $table->unsignedBigInteger('created_by')->nullable()->index();
                $table->unsignedBigInteger('updated_by')->nullable()->index();
                $table->timestamps();
                $table->softDeletes();

                $table->foreign('product_id')->references('id')->on('products')->onDelete('restrict');
                $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
                $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('damaged_products');
    }
};
