<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\CustomerOrderController;
use App\Http\Controllers\StorefrontController;
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

Route::middleware('auth')->group(function (): void {
    Route::get('orders', [CustomerOrderController::class, 'index'])->name('orders.index');
    Route::get('orders/{order}', [CustomerOrderController::class, 'show'])
        ->whereNumber('order')
        ->name('orders.show');
    Route::get('reserve', [CustomerOrderController::class, 'reservations'])->name('reserve');
    Route::get('checkout', [CustomerOrderController::class, 'checkout'])->name('checkout');

    Route::redirect('dashboard', '/admin')->name('dashboard');
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
});

require __DIR__.'/settings.php';
