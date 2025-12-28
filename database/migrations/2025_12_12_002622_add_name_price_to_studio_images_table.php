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
        Schema::table('studio_images', function (Blueprint $table) {
            $table->decimal('name_price', 10, 2)->default(5.00)->after('soft_copy_price');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('studio_images', function (Blueprint $table) {
            $table->dropColumn('name_price');
        });
    }
};
