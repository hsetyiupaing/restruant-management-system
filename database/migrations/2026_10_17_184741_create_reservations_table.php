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
        Schema::create('reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('table_id')->constrained('dining_tables')->restrictOnDelete();
            $table->unsignedSmallInteger('party_size');
            $table->dateTime('start_time');
            $table->dateTime('end_time');
            $table->enum('status', [
                'PENDING',
                'CONFIRMED',
                'SEATED',
                'COMPLETED',
                'CANCELLED',
                'NO_SHOW',
                'EXPIRED',
            ])->default('PENDING');
            $table->text('special_request')->nullable();
            $table->dateTime('hold_expires_at')->nullable();
            $table->timestamps();

            // Overlap lookups for a table's slot bookings.
            $table->index(['table_id', 'start_time', 'end_time']);
            // Reservation calendar filtered by restaurant and status.
            $table->index(['restaurant_id', 'status', 'start_time']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reservations');
    }
};
