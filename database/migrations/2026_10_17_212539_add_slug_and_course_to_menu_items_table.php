<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('menu_items', function (Blueprint $table) {
            // Slug is filled by the model on create and by the backfill below,
            // so it stays nullable only to keep any straggler row insertable.
            $table->string('slug')->nullable()->after('name');
            // Defaulting to MAIN keeps existing API clients that omit `course`
            // working; the admin form always sends an explicit course.
            $table->enum('course', ['STARTER', 'MAIN', 'DESSERT'])->default('MAIN')->after('slug');

            $table->unique(['restaurant_id', 'slug']);
            $table->index(['restaurant_id', 'course']);
        });

        $this->backfillSlugs();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('menu_items', function (Blueprint $table) {
            $table->dropUnique(['restaurant_id', 'slug']);
            $table->dropIndex(['restaurant_id', 'course']);
            $table->dropColumn(['slug', 'course']);
        });
    }

    /**
     * Give existing rows a stable, unique slug without touching their names.
     */
    private function backfillSlugs(): void
    {
        DB::table('menu_items')
            ->select(['id', 'restaurant_id', 'name'])
            ->orderBy('id')
            ->chunk(200, function ($items): void {
                foreach ($items as $item) {
                    $base = Str::slug($item->name) ?: 'dish';
                    $slug = $base;
                    $suffix = 2;

                    while (DB::table('menu_items')
                        ->where('restaurant_id', $item->restaurant_id)
                        ->where('slug', $slug)
                        ->exists()) {
                        $slug = "{$base}-{$suffix}";
                        $suffix++;
                    }

                    DB::table('menu_items')
                        ->where('id', $item->id)
                        ->update(['slug' => $slug]);
                }
            });
    }
};
