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
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();

            // Assuming only StudioImage is needed for now
            $table->foreignId('studio_image_id')->constrained()->cascadeOnDelete();
    
            $table->boolean('is_instant')->default(false);
            $table->boolean('include_soft_copy')->default(false);
            $table->decimal('price', 8, 2); // computed total price
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
