<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\UpdateUserRoleRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\RoleAssignmentService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

/**
 * Platform account administration. Every action is gated on the Super Admin
 * policy, so an Admin or Staff account cannot list or promote users.
 */
class UserController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', User::class);

        $users = User::query()
            ->when($request->filled('role'), fn ($query) => $query->where('role', $request->string('role')))
            ->when($request->filled('search'), function ($query) use ($request): void {
                $search = $request->string('search');
                $query->where(fn ($inner) => $inner
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%"));
            })
            ->orderBy('name')
            ->paginate(min(max($request->integer('per_page', 15), 1), 100))
            ->withQueryString();

        return UserResource::collection($users);
    }

    public function updateRole(
        UpdateUserRoleRequest $request,
        User $user,
        RoleAssignmentService $roles,
    ): UserResource {
        Gate::authorize('assignRole', $user);

        $roles->assign(
            $user,
            $request->enum('role', UserRole::class),
            $request->user(),
        );

        return new UserResource($user->refresh());
    }
}
