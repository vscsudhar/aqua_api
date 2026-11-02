<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_other_transactions', function (Blueprint $table) {
            $table->id();
            $table->date('transaction_date')->unique();
            $table->unsignedTinyInteger('period')->default(1); // 1 = days 1-15, 2 = days 16-end
            $table->unsignedInteger('cane_quantity')->default(0);
            $table->decimal('filling_amount', 12, 2)->default(0.00);
            $table->decimal('payments_received', 12, 2)->default(0.00);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['transaction_date', 'period']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_other_transactions');
    }
};
