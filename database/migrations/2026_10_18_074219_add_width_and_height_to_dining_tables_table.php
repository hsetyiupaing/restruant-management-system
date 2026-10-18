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
        Schema::table('dining_tables', function (Blueprint $table) {
            // Floor-plan footprint in canvas units. Defaults keep the existing
            // rows renderable as small squares until the plan is laid out.
            $table->unsignedSmallInteger('width')->default(90)->after('pos_y');
            $table->unsignedSmallInteger('height')->default(60)->after('width');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('dining_tables', function (Blueprint $table) {
            $table->dropColumn(['width', 'height']);
        });
    }
};
