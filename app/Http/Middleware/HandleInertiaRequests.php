<?php

namespace App\Http\Middleware;

use App\Support\RestaurantContext;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user(),
                // Saved restrictions travel with every page, so the dish page
                // can compare them with a dish without another request. Only
                // the signed-in customer's own rows are serialised.
                'foodRestrictions' => fn (): array => $this->foodRestrictions($request),
            ],
            'restaurantContext' => fn (): ?array => $this->restaurantContext($request),
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }

    /**
     * The signed-in customer's saved food restrictions. Guests get an empty
     * list, never a previously cached customer's data.
     *
     * @return array<int, array<string, mixed>>
     */
    private function foodRestrictions(Request $request): array
    {
        $user = $request->user();

        if ($user === null) {
            return [];
        }

        return $user->foodRestrictions()
            ->orderBy('kind')
            ->orderBy('restriction')
            ->get()
            ->map(fn ($restriction): array => [
                'restriction' => $restriction->restriction->value,
                'kind' => $restriction->kind->value,
                'label' => $restriction->label(),
                'custom_label' => $restriction->custom_label,
                'allergen' => $restriction->restriction->allergen()?->value,
                'dietary_tag' => $restriction->restriction->dietaryTag(),
            ])
            ->all();
    }

    /**
     * The restaurant a signed-in member administers, used by the back-office
     * layout for its navigation. Null for guests and plain customers.
     *
     * @return array{id: int, name: string, slug: string}|null
     */
    private function restaurantContext(Request $request): ?array
    {
        $restaurant = RestaurantContext::forUser($request->user());

        if ($restaurant === null) {
            return null;
        }

        return [
            'id' => $restaurant->id,
            'name' => $restaurant->name,
            'slug' => $restaurant->slug,
        ];
    }
}
