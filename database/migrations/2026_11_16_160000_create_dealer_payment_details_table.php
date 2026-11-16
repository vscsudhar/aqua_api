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
        // 1. Ensure dealer_payments.payment_mode and payment_method can store 'split', 'card', 'other', etc.
        if (Schema::hasTable('dealer_payments')) {
            try {
                // Change enum to VARCHAR(50)
                DB::statement("ALTER TABLE `dealer_payments` MODIFY `payment_mode` VARCHAR(50) NULL DEFAULT 'cash'");
                DB::statement("ALTER TABLE `dealer_payments` MODIFY `payment_method` VARCHAR(50) NOT NULL DEFAULT 'cash'");
            } catch (\Throwable $e) {
                // Ignore if already varchar
            }
        }

        // 2. Create dealer_payment_details table
        if (!Schema::hasTable('dealer_payment_details')) {
            Schema::create('dealer_payment_details', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->nullable()->index();
                $table->unsignedBigInteger('dealer_payment_id')->index();
                $table->string('payment_mode', 50)->default('cash')->index();
                $table->decimal('amount', 12, 2)->default(0.00);
                $table->string('reference_no', 100)->nullable();
                $table->string('notes', 255)->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->foreign('dealer_payment_id')
                    ->references('id')
                    ->on('dealer_payments')
                    ->onDelete('cascade');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dealer_payment_details');
    }
};
