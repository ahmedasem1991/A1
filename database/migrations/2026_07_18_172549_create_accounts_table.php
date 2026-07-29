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
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->nullable()->unique();
            $table->string('account_type')->index();
            $table->string('account_subtype')->nullable()->index();
            $table->boolean('is_bank_account')->default(false)->index();
            $table->boolean('is_system')->default(false);
            $table->text('description')->nullable();
            $table->decimal('opening_balance', 14, 2)->default(0);
            $table->decimal('current_balance_cache', 14, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('accounts');
    }
};
