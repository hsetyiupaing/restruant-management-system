<?php

namespace App\Http\Controllers;

use App\Enums\FoodRestriction;
use App\Http\Resources\FoodRestrictionResource;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The signed-in customer's saved allergies and dietary restrictions.
 *
 * The account page always edits the current user's own list; no id is accepted
 * from the request, so there is no way to address another customer.
 */
class CustomerRestrictionController extends Controller
{
    public function edit(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Customer/Restrictions', [
            'catalog' => FoodRestriction::catalog(),
            'saved' => FoodRestrictionResource::collection(
                $user->foodRestrictions()->orderBy('kind')->orderBy('restriction')->get(),
            )->resolve(),
        ]);
    }
}
