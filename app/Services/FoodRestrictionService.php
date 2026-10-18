<?php

namespace App\Services;

use App\Models\CustomerFoodRestriction;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Reads and replaces a customer's saved food restrictions.
 *
 * The caller must already have authorised access to the user: nothing here
 * resolves a customer from the request, so a request can never name someone
 * else's id.
 */
class FoodRestrictionService
{
    /**
     * Replace the customer's saved restrictions with exactly the given set.
     * An empty set clears the list — the "None" option writes no row.
     *
     * @param  array<int, array{restriction: string, kind: string, custom_label: string|null}>  $rows
     */
    public function replaceFor(User $user, array $rows): void
    {
        DB::transaction(function () use ($user, $rows): void {
            $keep = array_column($rows, 'restriction');

            $user->foodRestrictions()
                ->whereNotIn('restriction', $keep)
                ->delete();

            foreach ($rows as $row) {
                $user->foodRestrictions()->updateOrCreate(
                    ['restriction' => $row['restriction']],
                    ['kind' => $row['kind'], 'custom_label' => $row['custom_label']],
                );
            }
        });

        $user->unsetRelation('foodRestrictions');
    }

    /**
     * @return Collection<int, CustomerFoodRestriction>
     */
    public function forUser(User $user)
    {
        return $user->foodRestrictions()
            ->orderBy('kind')
            ->orderBy('restriction')
            ->get();
    }
}
