<?php

namespace App\Http\Controllers\Admin;

use App\Enums\StaffType;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\UpdateUserRoleRequest;
use App\Http\Requests\RemoveUserAccessRequest;
use App\Models\User;
use App\Services\RoleAssignmentService;
use Illuminate\Http\RedirectResponse;

class AccountAccessController extends Controller
{
    public function updateRole(
        UpdateUserRoleRequest $request,
        User $user,
        RoleAssignmentService $roles,
    ): RedirectResponse {
        $role = $request->enum('role', UserRole::class);
        $updated = $roles->assign(
            $user,
            $role,
            $request->enum('staff_type', StaffType::class),
            $request->hasStaffType(),
        );

        return back()->with('success', "{$updated->name} is now ".self::roleLabel($role).'.');
    }

    public function removeAccess(
        RemoveUserAccessRequest $request,
        User $user,
        RoleAssignmentService $roles,
    ): RedirectResponse {
        $updated = $roles->removeAccess($user);

        return back()->with('success', "Access removed. {$updated->name} is now a Customer.");
    }

    private static function roleLabel(UserRole $role): string
    {
        return match ($role) {
            UserRole::Customer => 'Customer',
            UserRole::Staff => 'Staff',
            UserRole::Admin => 'Administrator',
            UserRole::SuperAdmin => 'Super Admin',
        };
    }
}
