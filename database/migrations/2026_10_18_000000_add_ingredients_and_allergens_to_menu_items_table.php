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
        Schema::table('menu_items', function (Blueprint $table) {
            // Both columns are nullable because a kitchen may not have recorded
            // this information for an existing dish. Null means "not recorded"
            // and is shown to the customer as unknown, never as "allergen free".
            $table->text('ingredients')->nullable()->after('description');
            $table->json('allergens')->nullable()->after('ingredients');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('menu_items', function (Blueprint $table) {
            $table->dropColumn(['ingredients', 'allergens']);
        });
    }
};
