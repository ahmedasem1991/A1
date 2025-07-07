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
        Schema::table('order_items', function (Blueprint $table) {
          // Drop old foreign key constraints

            // Modify the foreign keys to use 'set null' on delete
            $table->foreignId('image_card_id')->nullable()->constrained()->onDelete('set null')->change();

            // Add new category column
            $table->string('category')->after('order_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
             // Drop new columns
            $table->dropColumn('category');
            $table->dropForeign(['studio_image_id']);
            $table->dropForeign(['image_card_id']);

            // Restore the old foreign keys with cascade on delete
            $table->foreignId('studio_image_id')->nullable()->constrained()->cascadeOnDelete()->change();
            $table->foreignId('image_card_id')->nullable()->constrained()->cascadeOnDelete()->change();

        });
    }
};
