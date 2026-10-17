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
        Schema::create('restaurants', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('phone')->nullable();
            $table->string('address')->nullable();
            $table->json('opening_hours')->nullable();
            $table->unsignedSmallInteger('slot_minutes')->default(90);
            $table->boolean('auto_confirm_reservations')->default(false);
            $table->decimal('delivery_fee', 8, 2)->default(0);
            $table->decimal('min_delivery_amount', 8, 2)->default(0);
            $table->decimal('tax_rate', 5, 4)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('restaurants');
    }
};
