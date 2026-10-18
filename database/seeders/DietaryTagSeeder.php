<?php

namespace Database\Seeders;

use App\Enums\DietaryRequirement;
use App\Models\DietaryTag;
use Illuminate\Database\Seeder;

/**
 * The fixed set of dietary classifications the storefront can filter by.
 * Idempotent: keyed on the slug, which is the value the API exchanges.
 */
class DietaryTagSeeder extends Seeder
{
    public function run(): void
    {
        foreach (DietaryRequirement::cases() as $requirement) {
            DietaryTag::query()->updateOrCreate(
                ['slug' => $requirement->value],
                [
                    'name' => $requirement->label(),
                    'description' => $requirement->description(),
                ],
            );
        }
    }
}
