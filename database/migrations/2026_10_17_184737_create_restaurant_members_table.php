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
        Schema::create('restaurant_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('role', ['STAFF', 'ADMIN'])->default('STAFF');
            $table->enum('staff_type', ['CASHIER', 'KITCHEN', 'WAITER', 'DRIVER'])->nullable();
            $table->timestamps();

            // One membership per person per restaurant.
            $table->unique(['restaurant_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('restaurant_members');
    }
};
