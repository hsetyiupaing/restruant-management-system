<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\RestaurantContext;
use Illuminate\Database\Eloquent\Builder;
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
     * Platform account administration. Gated here as well as in the API: the
     * page itself is only for Super Admins, even though the back office is
     * otherwise membership-based.
     */
    public function users(Request $request): Response
    {
        abort_unless($request->user()->isSuperAdmin(), 403);

        $search = trim($request->string('search')->toString());
        $role = $request->query('role');
        $role = is_string($role) ? $role : '';
        $page = max($request->integer('page', 1), 1);

        return Inertia::render('Admin/Users/Index', [
            'canAssignRoles' => true,
            'filters' => [
                'search' => $search,
                'role' => $role,
                'page' => $page,
            ],
            'users' => fn (): array => $this->platformUsers($search, $role, $page),
        ]);
    }

    /**
     * @return array{
     *     data: array<int, array<string, mixed>>,
     *     links: array{first: string|null, last: string|null, prev: string|null, next: string|null},
     *     meta: array{current_page: int, from: int|null, last_page: int, per_page: int, path: string, to: int|null, total: int}
     * }
     */
    private function platformUsers(string $search, string $role, int $page): array
    {
        $paginator = User::query()
            ->with('memberships')
            ->when($role !== '', fn (Builder $query) => $query->where('role', $role))
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(fn (Builder $users) => $users
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%"));
            })
            ->orderBy('name')
            ->paginate(20, ['*'], 'page', $page)
            ->withQueryString();

        return [
            'data' => $paginator->getCollection()->map(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'email_verified' => $user->email_verified_at !== null,
                'role' => $user->role->value,
                'staff_type' => $user->memberships
                    ->first(fn ($membership): bool => $membership->staff_type !== null)
                    ?->staff_type?->value,
                'no_show_count' => $user->no_show_count,
                'created_at' => $user->created_at?->toIso8601String(),
            ])->values()->all(),
            'links' => [
                'first' => $paginator->url(1),
                'last' => $paginator->url($paginator->lastPage()),
                'prev' => $paginator->previousPageUrl(),
                'next' => $paginator->nextPageUrl(),
            ],
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'from' => $paginator->firstItem(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'path' => $paginator->path(),
                'to' => $paginator->lastItem(),
                'total' => $paginator->total(),
            ],
        ];
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
