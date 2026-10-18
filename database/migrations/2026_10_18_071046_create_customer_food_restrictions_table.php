<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * One row per saved restriction, keyed on a stable code from
     * App\Enums\FoodRestriction. The code is normalised relational data rather
     * than a comma-separated string, so a value can be validated, translated
     * and indexed. `kind` duplicates the enum grouping so the stored row can be
     * inspected and filtered without loading PHP.
     */
    public function up(): void
    {
        Schema::create('customer_food_restrictions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('restriction', 64);
            $table->string('kind', 32);

            // Only "Other" entries carry free text; it is required for those
            // and kept null for the fixed vocabulary.
            $table->string('custom_label', 100)->nullable();
            $table->timestamps();

            // A customer saves each restriction at most once. "Other" entries
            // are one per type, with the text editable rather than repeated.
            $table->unique(['user_id', 'restriction']);
            $table->index(['kind', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_food_restrictions');
    }
};
