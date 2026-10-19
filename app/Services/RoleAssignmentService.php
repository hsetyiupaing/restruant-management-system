<?php

namespace App\Services;

use App\Enums\StaffType;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The single place a user's platform role may change.
 *
 * Super Admin accounts cannot be changed. This check runs on a locked row so
 * a concurrent promotion cannot be followed by an unauthorized demotion.
 */
class RoleAssignmentService
{
    /**
     * @throws AuthorizationException
     */
    public function assign(
        User $target,
        UserRole $role,
        ?StaffType $staffType = null,
        bool $updateStaffType = false,
    ): User {
        return DB::transaction(function () use ($target, $role, $staffType, $updateStaffType): User {
            $locked = User::query()->lockForUpdate()->findOrFail($target->id);

            if ($locked->isSuperAdmin()) {
                throw new AuthorizationException('Super Admin accounts cannot be changed.');
            }

            $locked->role = $role;
            $locked->save();

            if (Schema::hasTable('restaurant_members')) {
                if ($role === UserRole::Staff && $updateStaffType) {
                    DB::table('restaurant_members')
                        ->where('user_id', $locked->id)
                        ->update(['staff_type' => $staffType?->value]);
                } elseif ($role !== UserRole::Staff) {
                    DB::table('restaurant_members')
                        ->where('user_id', $locked->id)
                        ->update(['staff_type' => null]);
                }
            }

            return $locked;
        });
    }

    /**
     * @throws AuthorizationException
     */
    public function removeAccess(User $target): User
    {
        return DB::transaction(function () use ($target): User {
            $locked = User::query()->lockForUpdate()->findOrFail($target->id);

            if ($locked->isSuperAdmin()) {
                throw new AuthorizationException('Super Admin accounts cannot be changed.');
            }

            $locked->role = UserRole::Customer;
            $locked->save();

            if (Schema::hasTable('restaurant_members')) {
                DB::table('restaurant_members')
                    ->where('user_id', $locked->id)
                    ->delete();
            }

            if (config('session.driver') === 'database') {
                $connection = config('session.connection');
                $table = config('session.table', 'sessions');
                $schema = Schema::connection($connection);

                if ($schema->hasTable($table) && $schema->hasColumn($table, 'user_id')) {
                    DB::connection($connection)
                        ->table($table)
                        ->where('user_id', $locked->id)
                        ->delete();
                }
            }

            return $locked;
        });
    }
}
