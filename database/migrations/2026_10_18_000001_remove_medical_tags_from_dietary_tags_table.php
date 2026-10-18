<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Allergy and intolerance tags are not dietary classifications: the menu
     * may only filter by Vegetarian, Vegan and Keto. Their pivot rows are
     * removed by the foreign key cascade.
     */
    public function up(): void
    {
        DB::table('dietary_tags')
            ->whereIn('slug', ['nut-allergy', 'gluten-free', 'lactose-intolerance'])
            ->delete();
    }

    /**
     * The removed tags are not recreated: a rollback restores the old schema,
     * not the allergy classification that was deliberately retired.
     */
    public function down(): void
    {
        //
    }
};
