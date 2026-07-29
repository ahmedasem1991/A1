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
        Schema::create('supplier_bills', function (Blueprint $table) {
            $table->id();
            $table->string('bill_number')->unique();
            $table->foreignId('supplier_id')->constrained('suppliers')->restrictOnDelete();
            $table->date('bill_date')->index();
            $table->date('due_date')->nullable()->index();
            $table->decimal('total_amount', 14, 2);
            $table->decimal('paid_amount_cache', 14, 2)->default(0);
            $table->decimal('balance_cache', 14, 2);
            $table->string('status')->index();
            $table->foreignId('expense_account_id')->nullable()->constrained('accounts')->restrictOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['supplier_id', 'bill_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('supplier_bills');
    }
};
