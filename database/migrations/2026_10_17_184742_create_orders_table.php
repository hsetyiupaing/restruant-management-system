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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            // Orders are financial records: deleting a restaurant must not erase them.
            $table->foreignId('restaurant_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('reservation_id')->nullable()->constrained()->nullOnDelete();
            $table->string('order_number', 32)->unique();
            $table->enum('order_type', ['PICKUP', 'DELIVERY']);
            $table->enum('status', [
                'PLACED',
                'CONFIRMED',
                'PREPARING',
                'READY',
                'OUT_FOR_DELIVERY',
                'COMPLETED',
                'CANCELLED',
                'REJECTED',
                'DELIVERY_FAILED',
                'REFUNDED',
            ])->default('PLACED');
            $table->enum('payment_status', ['UNPAID', 'PAID', 'FAILED', 'REFUNDED'])
                ->default('UNPAID');
            $table->decimal('subtotal', 10, 2)->default(0);
            $table->decimal('delivery_fee', 10, 2)->default(0);
            $table->decimal('tax', 10, 2)->default(0);
            $table->decimal('discount', 10, 2)->default(0);
            $table->decimal('total', 10, 2)->default(0);
            $table->dateTime('scheduled_for')->nullable();
            $table->text('customer_note')->nullable();
            $table->timestamps();

            // Live order queue and status filters for one restaurant.
            $table->index(['restaurant_id', 'status', 'created_at']);
            // A customer's "My Orders" history.
            $table->index(['customer_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
