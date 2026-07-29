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
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->string('expense_number')->unique();
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->restrictOnDelete();
            $table->foreignId('expense_account_id')->constrained('accounts')->restrictOnDelete();
            $table->decimal('amount', 14, 2);
            $table->date('expense_date')->index();
            $table->foreignId('payment_id')->nullable()->constrained('payments')->restrictOnDelete();
            $table->string('description');
            $table->foreignId('recorded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
