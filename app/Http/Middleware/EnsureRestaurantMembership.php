<?php

namespace App\Http\Middleware;

use App\Enums\MembershipRole;
use App\Models\Restaurant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authorize the authenticated user against the restaurant bound to the route.
 *
 * Platform administrators (ADMIN / SUPER_ADMIN) bypass membership checks.
 * Everyone else needs a restaurant_members row; an "admin" requirement also
 * demands that the membership role is ADMIN.
 */
class EnsureRestaurantMembership
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string $level = 'staff'): Response
    {
        $restaurant = $request->route('restaurant');

        abort_unless($restaurant instanceof Restaurant, 404);

        $user = $request->user();

        abort_if($user === null, 401);

        if ($user->isPlatformAdministrator()) {
            return $next($request);
        }

        $requiredRole = $level === 'admin' ? MembershipRole::Admin : MembershipRole::Staff;

        abort_unless($restaurant->hasMember($user, $requiredRole), 403, 'You are not a member of this restaurant.');

        return $next($request);
    }
}
