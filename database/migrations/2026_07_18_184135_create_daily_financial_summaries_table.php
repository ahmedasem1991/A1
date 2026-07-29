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
        Schema::create('daily_financial_summaries', function (Blueprint $table) {
            $table->id();
            $table->date('summary_date')->unique();
            $table->decimal('total_sales', 14, 2)->default(0);
            $table->decimal('total_income', 14, 2)->default(0);
            $table->decimal('total_expenses', 14, 2)->default(0);
            $table->decimal('net_profit', 14, 2)->default(0);
            $table->decimal('payments_received', 14, 2)->default(0);
            $table->decimal('payments_made', 14, 2)->default(0);
            $table->decimal('refunds_issued', 14, 2)->default(0);
            $table->decimal('outstanding_receivables_eod', 14, 2)->default(0);
            $table->decimal('outstanding_payables_eod', 14, 2)->default(0);
            $table->decimal('cash_balance_eod', 14, 2)->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('daily_financial_summaries');
    }
};
