<?php

use App\Http\Controllers\Admin\AccountAccessController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\CustomerOrderController;
use App\Http\Controllers\CustomerRestrictionController;
use App\Http\Controllers\StorefrontController;
use App\Support\HomePath;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Storefront and account pages
|--------------------------------------------------------------------------
|
| These routes render the Inertia shells. Page data is loaded from the v1 API
| by the Vue components, except for the public catalogue which is server-rendered.
|
*/

Route::get('/', [StorefrontController::class, 'home'])->name('home');
Route::get('menu', [StorefrontController::class, 'menu'])->name('menu');
Route::get('menu/{dish}', [StorefrontController::class, 'dish'])->name('menu.show');

Route::middleware('auth')->group(function (): void {
    Route::get('orders', [CustomerOrderController::class, 'index'])->name('orders.index');
    Route::get('orders/{order}', [CustomerOrderController::class, 'show'])
        ->whereNumber('order')
        ->name('orders.show');
    Route::get('reserve', [CustomerOrderController::class, 'reservations'])->name('reserve');
    Route::get('checkout', [CustomerOrderController::class, 'checkout'])->name('checkout');

    // A customer's own saved restrictions. The account is resolved from the
    // session, so there is no route parameter to tamper with.
    Route::get('account/dietary-restrictions', [CustomerRestrictionController::class, 'edit'])
        ->name('account.restrictions.edit');

    // Customers have no back office, so this cannot be a fixed redirect to
    // /admin (which would 403 for them). Fortify also reads it after login.
    Route::get('dashboard', fn (Request $request) => redirect(HomePath::forUser($request->user())))
        ->name('dashboard');
});

/*
|--------------------------------------------------------------------------
| Restaurant back office
|--------------------------------------------------------------------------
|
| Authorization is enforced by RestaurantContext: the user needs an ADMIN
| membership (or a platform administrator role) to render any of these pages.
|
*/

Route::middleware('auth')->prefix('admin')->name('admin.')->group(function (): void {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('orders', [DashboardController::class, 'orders'])->name('orders.index');
    Route::get('orders/{order}', [DashboardController::class, 'order'])
        ->whereNumber('order')
        ->name('orders.show');
    Route::get('reservations', [DashboardController::class, 'reservations'])->name('reservations.index');
    Route::get('menu', [DashboardController::class, 'menu'])->name('menu.index');
    Route::get('customers', [DashboardController::class, 'customers'])->name('customers.index');
    Route::get('settings', [DashboardController::class, 'settings'])->name('settings.index');

    // Super Admin only; the controller aborts 403 for everyone else because
    // this page is not scoped to a single restaurant.
    Route::get('users', [DashboardController::class, 'users'])->name('users.index');
    Route::patch('accounts/{user}/role', [AccountAccessController::class, 'updateRole'])
        ->name('accounts.role.update');
    Route::post('accounts/{user}/remove-access', [AccountAccessController::class, 'removeAccess'])
        ->name('accounts.remove-access');
});

require __DIR__.'/settings.php';
