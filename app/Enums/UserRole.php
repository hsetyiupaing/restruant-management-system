<?php

namespace App\Enums;

enum UserRole: string
{
    case Customer = 'CUSTOMER';
    case Staff = 'STAFF';
    case Admin = 'ADMIN';
    case SuperAdmin = 'SUPER_ADMIN';

    /**
     * Platform-level administrators who may manage any restaurant.
     */
    public function isPlatformAdministrator(): bool
    {
        return in_array($this, [self::Admin, self::SuperAdmin], true);
    }
}
