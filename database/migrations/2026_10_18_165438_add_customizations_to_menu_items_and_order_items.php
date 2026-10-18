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
            $table->json('customization_groups')->nullable()->after('is_featured');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->json('selected_customizations')->nullable()->after('note');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn('selected_customizations');
        });

        Schema::table('menu_items', function (Blueprint $table) {
            $table->dropColumn('customization_groups');
        });
    }
};
