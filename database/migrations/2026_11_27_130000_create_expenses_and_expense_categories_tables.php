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
        if (! Schema::hasTable('expense_categories')) {
            Schema::create('expense_categories', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->nullable()->constrained('companies')->cascadeOnDelete();
                $table->string('name', 120);
                $table->string('description', 255)->nullable();
                $table->boolean('is_default')->default(false)->index();
                $table->string('status', 20)->default('active')->index();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['company_id', 'status']);
                $table->index(['company_id', 'name']);
            });
        }

        if (! Schema::hasTable('expenses')) {
            Schema::create('expenses', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
                $table->foreignId('category_id')->constrained('expense_categories')->restrictOnDelete();
                $table->decimal('amount', 12, 2);
                $table->date('expense_date')->index();
                $table->string('expense_time', 20)->nullable();
                $table->string('payment_mode', 20)->index();
                $table->string('paid_to', 150)->nullable();
                $table->text('description')->nullable();
                $table->string('attachment_path', 255)->nullable();
                $table->string('attachment_original_name', 255)->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['company_id', 'expense_date']);
                $table->index(['company_id', 'category_id', 'expense_date']);
                $table->index(['company_id', 'payment_mode', 'expense_date']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expenses');
        Schema::dropIfExists('expense_categories');
    }
};
