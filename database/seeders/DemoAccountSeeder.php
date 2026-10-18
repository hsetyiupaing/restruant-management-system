<?php

namespace Database\Seeders;

use App\Enums\MembershipRole;
use App\Enums\StaffType;
use App\Enums\UserRole;
use App\Models\Restaurant;
use App\Models\RestaurantMember;
use App\Models\User;
use App\Services\FoodRestrictionService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * =============================================================================
 * DEVELOPMENT-ONLY DEMO ACCOUNTS — NEVER DEPLOY THESE TO PRODUCTION
 * =============================================================================
 *
 * These accounts exist so a developer can sign in as each role. Their
 * passwords are published in the README, so any deployment containing them is
 * compromised by definition. The seeder refuses to run in production.
 *
 * Idempotent in the ways that matter:
 *  - an existing account is recognised by email and its password is NOT reset,
 *    so a developer who changed their local password keeps it;
 *  - the role is enforced back to the documented one (a demo account must
 *    actually demonstrate its documented role);
 *  - the demo customer's restrictions are upserted, never duplicated, and no
 *    other row is touched.
 *
 * Run it with: php artisan db:seed --class=DemoAccountSeeder
 */
class DemoAccountSeeder extends Seeder
{
    public const PASSWORD_SUPER_ADMIN = 'MoriSuperAdmin123!';

    public const PASSWORD_ADMIN = 'MoriAdmin123!';

    public const PASSWORD_STAFF = 'MoriStaff123!';

    public const PASSWORD_CUSTOMER = 'MoriCustomer123!';

    /**
     * @var array<int, array{name: string, email: string, password: string, role: UserRole}>
     */
    private array $accounts = [
        [
            'name' => 'Sam Superadmin',
            'email' => 'superadmin@mori.demo',
            'password' => self::PASSWORD_SUPER_ADMIN,
            'role' => UserRole::SuperAdmin,
        ],
        [
            'name' => 'Alex Admin',
            'email' => 'admin@mori.demo',
            'password' => self::PASSWORD_ADMIN,
            'role' => UserRole::Admin,
        ],
        [
            'name' => 'Stevie Staff',
            'email' => 'staff@mori.demo',
            'password' => self::PASSWORD_STAFF,
            'role' => UserRole::Staff,
        ],
        [
            'name' => 'Cassie Customer',
            'email' => 'customer@mori.demo',
            'password' => self::PASSWORD_CUSTOMER,
            'role' => UserRole::Customer,
        ],
    ];

    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command?->warn('DemoAccountSeeder is development-only and was skipped in production.');

            return;
        }

        DB::transaction(function (): void {
            $users = $this->seedAccounts();

            $this->seedRestaurantMemberships($users);
            $this->seedCustomerRestrictions($users['customer']);
        });
    }

    /**
     * @return array<string, User>
     */
    private function seedAccounts(): array
    {
        $users = [];

        foreach ($this->accounts as $account) {
            $user = User::query()->where('email', $account['email'])->first();

            if ($user === null) {
                $user = User::query()->create([
                    'name' => $account['name'],
                    'email' => $account['email'],
                    // Demo accounts are created verified: there is no mailbox to
                    // click a link in.
                    'email_verified_at' => now(),
                    'password' => Hash::make($account['password']),
                    'role' => $account['role'],
                    'no_show_count' => 0,
                ]);
            } else {
                // Keep the name and role in sync with the documentation but
                // never overwrite the password of an existing account.
                $user->update([
                    'name' => $account['name'],
                    'role' => $account['role'],
                ]);
            }

            $users[$account['role'] === UserRole::Customer ? 'customer' : $account['role']->value] = $user;
        }

        return $users;
    }

    /**
     * Give the admin and staff demo accounts a restaurant to operate. Super
     * Admins do not need one: the policy lets them act on any restaurant.
     *
     * @param  array<string, User>  $users
     */
    private function seedRestaurantMemberships(array $users): void
    {
        $restaurant = $this->storefrontRestaurant();

        if ($restaurant === null) {
            return;
        }

        if (isset($users[UserRole::Admin->value])) {
            $this->assignMembership($restaurant, $users[UserRole::Admin->value], MembershipRole::Admin, null);
        }

        if (isset($users[UserRole::Staff->value])) {
            $this->assignMembership($restaurant, $users[UserRole::Staff->value], MembershipRole::Staff, StaffType::Waiter);
        }
    }

    private function assignMembership(
        Restaurant $restaurant,
        User $user,
        MembershipRole $role,
        ?StaffType $staffType,
    ): void {
        RestaurantMember::query()->updateOrCreate(
            ['restaurant_id' => $restaurant->id, 'user_id' => $user->id],
            ['role' => $role, 'staff_type' => $staffType],
        );
    }

    /**
     * Example restrictions so the dish page has something to compare against:
     * two allergies and a dietary preference. Written through the same service
     * the account screen uses, so the stored shape cannot drift.
     */
    private function seedCustomerRestrictions(User $customer): void
    {
        app(FoodRestrictionService::class)->replaceFor($customer, [
            ['restriction' => 'peanuts', 'kind' => 'ALLERGY', 'custom_label' => null],
            ['restriction' => 'shellfish', 'kind' => 'ALLERGY', 'custom_label' => null],
            ['restriction' => 'vegetarian', 'kind' => 'DIETARY_PREFERENCE', 'custom_label' => null],
        ]);
    }

    private function storefrontRestaurant(): ?Restaurant
    {
        return Restaurant::query()
            ->where('slug', config('restaurant.storefront_slug'))
            ->first()
            ?? Restaurant::query()->orderBy('name')->first();
    }
}
