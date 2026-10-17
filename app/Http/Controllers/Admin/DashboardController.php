<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\RestaurantContext;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Back-office shells. Every page resolves the restaurant the signed-in member
 * administers and then reads its data from the v1 admin API.
 */
class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        return $this->render($request, 'Admin/Dashboard');
    }

    public function orders(Request $request): Response
    {
        return $this->render($request, 'Admin/Orders/Index');
    }

    public function order(Request $request, int $order): Response
    {
        return $this->render($request, 'Admin/Orders/Show', ['orderId' => $order]);
    }

    public function reservations(Request $request): Response
    {
        return $this->render($request, 'Admin/Reservations/Index');
    }

    public function menu(Request $request): Response
    {
        return $this->render($request, 'Admin/Menu/Index');
    }

    public function customers(Request $request): Response
    {
        return $this->render($request, 'Admin/Customers/Index');
    }

    public function settings(Request $request): Response
    {
        return $this->render($request, 'Admin/Settings/Index');
    }

    /**
     * @param  array<string, mixed>  $props
     */
    private function render(Request $request, string $page, array $props = []): Response
    {
        $user = $request->user();
        $restaurant = RestaurantContext::forUser($user);

        abort_if($restaurant === null, 403, 'You do not manage a restaurant.');

        return Inertia::render($page, [
            'restaurant' => [
                'id' => $restaurant->id,
                'name' => $restaurant->name,
                'slug' => $restaurant->slug,
            ],
            'canManageMenu' => RestaurantContext::canManage($user, $restaurant),
            ...$props,
        ]);
    }
}
