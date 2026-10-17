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
        Schema::table('users', function (Blueprint $table) {
            // Platform-wide role. Per-restaurant access lives in restaurant_members.
            $table->enum('role', ['CUSTOMER', 'STAFF', 'ADMIN', 'SUPER_ADMIN'])
                ->default('CUSTOMER')
                ->after('password');

            // Incremented on every NO_SHOW; the restaurant may require manual
            // approval once a diner passes the threshold in structure.md.
            $table->unsignedInteger('no_show_count')
                ->default(0)
                ->after('role');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['role', 'no_show_count']);
        });
    }
};
