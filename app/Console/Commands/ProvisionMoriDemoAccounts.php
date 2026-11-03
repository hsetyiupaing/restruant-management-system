<?php

namespace App\Console\Commands;

use App\Enums\MembershipRole;
use App\Enums\StaffType;
use App\Enums\UserRole;
use App\Models\Restaurant;
use App\Models\RestaurantMember;
use App\Models\User;
use App\Services\FoodRestrictionService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

#[Signature('app:provision-mori-demo-accounts {--confirm : Explicitly authorize account creation}')]
#[Description('Provision MORI demo accounts using production-only credentials')]
class ProvisionMoriDemoAccounts extends Command
{
    public function handle(): int
    {
        if (! app()->environment('production')) {
            $this->error('This command is restricted to the production environment.');

            return self::FAILURE;
        }

        if (! $this->option('confirm')) {
            $this->error('Pass --confirm to authorize account provisioning.');

            return self::FAILURE;
        }

        $restaurant = Restaurant::query()
            ->where('slug', config('restaurant.storefront_slug'))
            ->first()
            ?? Restaurant::query()->orderBy('name')->first();

        if ($restaurant === null) {
            $this->error('No restaurant exists. Create a restaurant before provisioning accounts.');

            return self::FAILURE;
        }

        $accounts = [
            [
                'name' => 'Sam Superadmin',
                'email' => 'superadmin@mori.demo',
                'role' => UserRole::SuperAdmin,
                'password' => env('MORI_SUPERADMIN_PASSWORD'),
            ],
            [
                'name' => 'Alex Admin',
                'email' => 'admin@mori.demo',
                'role' => UserRole::Admin,
                'password' => env('MORI_ADMIN_PASSWORD'),
            ],
            [
                'name' => 'Stevie Staff',
                'email' => 'staff@mori.demo',
                'role' => UserRole::Staff,
                'password' => env('MORI_STAFF_PASSWORD'),
            ],
            [
                'name' => 'Cassie Customer',
                'email' => 'customer@mori.demo',
                'role' => UserRole::Customer,
                'password' => env('MORI_CUSTOMER_PASSWORD'),
            ],
        ];

        try {
            Validator::make(
                collect($accounts)->mapWithKeys(
                    fn (array $account): array => [
                        str_replace(['@', '.'], '_', $account['email']) => [
                            'required',
                            'string',
                            'min:16',
                        ],
                    ],
                )->all(),
                [],
            );
        } catch (ValidationException) {
            // Credential validation is performed explicitly below.
        }

        foreach ($accounts as $account) {
            if (! is_string($account['password']) || strlen($account['password']) < 16) {
                $this->error("Missing or weak production password for {$account['email']}.");

                return self::FAILURE;
            }

            if (User::query()->where('email', $account['email'])->exists()) {
                $this->error("Account already exists: {$account['email']}. No accounts were changed.");

                return self::FAILURE;
            }
        }

        DB::transaction(function () use ($accounts, $restaurant): void {
            $users = [];

            foreach ($accounts as $account) {
                $users[$account['role']->value] = User::query()->create([
                    'name' => $account['name'],
                    'email' => $account['email'],
                    'email_verified_at' => now(),
                    'password' => Hash::make($account['password']),
                    'role' => $account['role'],
                    'no_show_count' => 0,
                ]);
            }

            RestaurantMember::query()->create([
                'restaurant_id' => $restaurant->id,
                'user_id' => $users[UserRole::Admin->value]->id,
                'role' => MembershipRole::Admin,
                'staff_type' => null,
            ]);

            RestaurantMember::query()->create([
                'restaurant_id' => $restaurant->id,
                'user_id' => $users[UserRole::Staff->value]->id,
                'role' => MembershipRole::Staff,
                'staff_type' => StaffType::Waiter,
            ]);

            app(FoodRestrictionService::class)->replaceFor(
                $users[UserRole::Customer->value],
                [
                    ['restriction' => 'peanuts', 'kind' => 'ALLERGY', 'custom_label' => null],
                    ['restriction' => 'shellfish', 'kind' => 'ALLERGY', 'custom_label' => null],
                    ['restriction' => 'vegetarian', 'kind' => 'DIETARY_PREFERENCE', 'custom_label' => null],
                ],
            );
        });

        $this->info('MORI demo accounts provisioned successfully.');
        $this->line("Restaurant: {$restaurant->name}");
        $this->line('Created 4 accounts and 2 restaurant memberships.');

        return self::SUCCESS;
    }
}
