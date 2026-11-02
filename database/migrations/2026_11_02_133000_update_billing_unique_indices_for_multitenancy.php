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
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropUnique('invoices_invoice_no_unique');
            $table->unique(['company_id', 'invoice_no'], 'invoices_company_id_invoice_no_unique');
        });

        Schema::table('invoice_payments', function (Blueprint $table) {
            $table->dropUnique('invoice_payments_payment_no_unique');
            $table->unique(['company_id', 'payment_no'], 'invoice_payments_company_id_payment_no_unique');
        });

        Schema::table('credit_notes', function (Blueprint $table) {
            $table->dropUnique('credit_notes_credit_note_no_unique');
            $table->unique(['company_id', 'credit_note_no'], 'credit_notes_company_id_credit_note_no_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('credit_notes', function (Blueprint $table) {
            $table->dropUnique('credit_notes_company_id_credit_note_no_unique');
            $table->unique('credit_note_no', 'credit_notes_credit_note_no_unique');
        });

        Schema::table('invoice_payments', function (Blueprint $table) {
            $table->dropUnique('invoice_payments_company_id_payment_no_unique');
            $table->unique('payment_no', 'invoice_payments_payment_no_unique');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropUnique('invoices_company_id_invoice_no_unique');
            $table->unique('invoice_no', 'invoices_invoice_no_unique');
        });
    }
};

