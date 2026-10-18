<?php

namespace Database\Seeders;

use App\Enums\TableShape;
use App\Models\DiningTable;
use App\Models\Restaurant;
use Illuminate\Database\Seeder;

/**
 * The MORI floor plan: twelve tables laid out on an 800x520 canvas that the
 * Reserve page renders 1:1 through an SVG viewBox, so a coordinate here is a
 * pixel there.
 *
 * Idempotent: each table is keyed on the restaurant and its label, so re-running
 * the seeder moves a table rather than creating a duplicate. It only ever
 * touches the tables in this list; nothing is deleted.
 *
 * Run it with: php artisan db:seed --class=DiningTableSeeder
 */
class DiningTableSeeder extends Seeder
{
    private const RESTAURANT_SLUG = 'mori-thai-kitchen';

    private const CANVAS_WIDTH = 800;

    private const CANVAS_HEIGHT = 520;

    /**
     * @var array<int, array{label: string, capacity: int, x: int, y: int, width: int, height: int}>
     */
    private array $tables = [
        ['label' => '1', 'capacity' => 2, 'x' => 40, 'y' => 40, 'width' => 90, 'height' => 60],
        ['label' => '2', 'capacity' => 2, 'x' => 160, 'y' => 40, 'width' => 90, 'height' => 60],
        ['label' => '3', 'capacity' => 2, 'x' => 280, 'y' => 40, 'width' => 90, 'height' => 60],
        ['label' => '4', 'capacity' => 2, 'x' => 400, 'y' => 40, 'width' => 90, 'height' => 60],
        ['label' => '5', 'capacity' => 4, 'x' => 40, 'y' => 170, 'width' => 130, 'height' => 80],
        ['label' => '6', 'capacity' => 4, 'x' => 210, 'y' => 170, 'width' => 130, 'height' => 80],
        ['label' => '7', 'capacity' => 4, 'x' => 380, 'y' => 170, 'width' => 130, 'height' => 80],
        ['label' => '8', 'capacity' => 6, 'x' => 40, 'y' => 310, 'width' => 170, 'height' => 90],
        ['label' => '9', 'capacity' => 6, 'x' => 250, 'y' => 310, 'width' => 170, 'height' => 90],
        ['label' => '10', 'capacity' => 4, 'x' => 600, 'y' => 70, 'width' => 130, 'height' => 80],
        ['label' => '11', 'capacity' => 4, 'x' => 600, 'y' => 180, 'width' => 130, 'height' => 80],
        ['label' => '12', 'capacity' => 8, 'x' => 540, 'y' => 310, 'width' => 220, 'height' => 100],
    ];

    public function run(): void
    {
        $restaurant = $this->restaurant();

        if ($restaurant === null) {
            $this->command?->warn(
                'DiningTableSeeder skipped: no restaurant with slug "'.self::RESTAURANT_SLUG.'".'
            );

            return;
        }

        foreach ($this->tables as $row) {
            $this->assertInsideCanvas($row);

            DiningTable::query()->updateOrCreate(
                ['restaurant_id' => $restaurant->id, 'label' => $row['label']],
                [
                    'capacity' => $row['capacity'],
                    'min_capacity' => 1,
                    'pos_x' => $row['x'],
                    'pos_y' => $row['y'],
                    'width' => $row['width'],
                    'height' => $row['height'],
                    'shape' => TableShape::Rect,
                    'is_active' => true,
                ],
            );
        }

        $this->command?->info(
            count($this->tables).' tables laid out for '.$restaurant->name.'.'
        );
    }

    private function restaurant(): ?Restaurant
    {
        return Restaurant::query()->where('slug', self::RESTAURANT_SLUG)->first();
    }

    /**
     * A table outside the canvas would be invisible or clipped on the Reserve
     * page, which looks like the floor plan silently failing.
     *
     * @param  array{label: string, capacity: int, x: int, y: int, width: int, height: int}  $row
     */
    private function assertInsideCanvas(array $row): void
    {
        $fits = $row['x'] + $row['width'] <= self::CANVAS_WIDTH
            && $row['y'] + $row['height'] <= self::CANVAS_HEIGHT;

        if (! $fits) {
            throw new \RuntimeException(
                "Table {$row['label']} falls outside the ".self::CANVAS_WIDTH.'x'.self::CANVAS_HEIGHT.' floor plan.'
            );
        }
    }
}
