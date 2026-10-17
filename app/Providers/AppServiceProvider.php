<?php

namespace App\Providers;

use App\Models\DeliveryDetail;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\Restaurant;
use App\Policies\DeliveryDetailPolicy;
use App\Policies\OrderPolicy;
use App\Policies\PaymentPolicy;
use App\Policies\ReservationPolicy;
use App\Policies\RestaurantPolicy;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureAuthorization();
    }

    /**
     * Laravel discovers these automatically; registering them explicitly keeps
     * the mapping in one place next to the domain models.
     */
    protected function configureAuthorization(): void
    {
        Gate::policy(Restaurant::class, RestaurantPolicy::class);
        Gate::policy(Order::class, OrderPolicy::class);
        Gate::policy(Reservation::class, ReservationPolicy::class);
        Gate::policy(DeliveryDetail::class, DeliveryDetailPolicy::class);
        Gate::policy(Payment::class, PaymentPolicy::class);
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
