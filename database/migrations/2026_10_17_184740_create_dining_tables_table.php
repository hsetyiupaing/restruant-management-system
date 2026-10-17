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
        Schema::create('dining_tables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->unsignedSmallInteger('capacity');
            $table->unsignedSmallInteger('min_capacity')->default(1);
            $table->integer('pos_x')->default(0);
            $table->integer('pos_y')->default(0);
            $table->enum('shape', ['ROUND', 'SQUARE', 'RECT'])->default('ROUND');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Table labels are unique within a restaurant, not across the platform.
            $table->unique(['restaurant_id', 'label']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dining_tables');
    }
};
