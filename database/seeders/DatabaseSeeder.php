<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Demo data is development-only. It is idempotent, but never run it
        // automatically against production: `php artisan db:seed --class=DemoDataSeeder`.
        if (app()->environment('production')) {
            return;
        }

        $this->call(DemoDataSeeder::class);
    }
}
