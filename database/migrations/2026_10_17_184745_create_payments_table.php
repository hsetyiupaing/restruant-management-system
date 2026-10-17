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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            // Payments are financial records: keep them if an order is removed.
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->enum('method', ['CASH', 'CARD', 'ONLINE']);
            $table->decimal('amount', 10, 2);
            $table->enum('status', ['UNPAID', 'PAID', 'FAILED', 'REFUNDED'])
                ->default('UNPAID');
            $table->string('provider_ref')->nullable();
            $table->dateTime('paid_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
