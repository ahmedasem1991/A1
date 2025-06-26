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
        Schema::create('studio_images', function (Blueprint $table) {
            $table->id();
            $table->string('image_size');
            $table->integer('image_count');
            $table->decimal('price', 8, 2);
            $table->decimal('instant_price', 8, 2)->nullable();
            $table->decimal('soft_copy_price', 8, 2)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('studio_images');
    }
};
