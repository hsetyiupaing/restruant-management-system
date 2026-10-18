<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The single place a user's platform role may change.
 *
 * Two invariants live here rather than in a controller: the platform must
 * always keep at least one Super Admin, and a Super Admin cannot demote
 * themselves — that is how an operator locks themselves out of role
 * management. Both checks run on a locked row so two concurrent demotions
 * cannot both succeed.
 */
class RoleAssignmentService
{
    /**
     * @param  User|null  $actor  The administrator making the change, when
     *                            known. Used only to reject self-demotion.
     *
     * @throws ValidationException
     */
    public function assign(User $target, UserRole $role, ?User $actor = null): User
    {
        if ($target->role === $role) {
            return $target;
        }

        return DB::transaction(function () use ($target, $role, $actor): User {
            $locked = User::query()->lockForUpdate()->findOrFail($target->id);

            if ($locked->role === UserRole::SuperAdmin && $role !== UserRole::SuperAdmin) {
                $this->ensureNotLastSuperAdmin($locked);

                if ($actor !== null && $actor->id === $locked->id) {
                    throw ValidationException::withMessages([
                        'role' => 'You cannot change your own Super Admin role.',
                    ]);
                }
            }

            $locked->role = $role;
            $locked->save();

            return $locked;
        });
    }

    /**
     * @throws ValidationException
     */
    private function ensureNotLastSuperAdmin(User $user): void
    {
        $remaining = User::query()
            ->where('role', UserRole::SuperAdmin)
            ->whereKeyNot($user->id)
            ->lockForUpdate()
            ->count();

        if ($remaining === 0) {
            throw ValidationException::withMessages([
                'role' => 'The last Super Admin cannot be demoted. Assign another Super Admin first.',
            ]);
        }
    }
}
