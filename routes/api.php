<?php

use App\Http\Controllers\Api\Admin;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PublicRestaurantController;
use App\Http\Controllers\Api\ReservationController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API v1
|--------------------------------------------------------------------------
|
| Session-cookie authenticated (Sanctum stateful) for the first-party Vue SPA.
| Stateful requests must first GET /sanctum/csrf-cookie. Authorization is
| enforced by policies and the restaurant.membership middleware — never by the
| frontend alone.
|
*/

Route::prefix('v1')->group(function (): void {
    // ---------- Public ----------
    Route::get('restaurants', [PublicRestaurantController::class, 'index']);
    Route::get('restaurants/{slug}', [PublicRestaurantController::class, 'show']);
    Route::get('restaurants/{slug}/categories', [PublicRestaurantController::class, 'categories']);
    Route::get('restaurants/{slug}/menu-items', [PublicRestaurantController::class, 'menuItems']);
    Route::get('dietary-tags', [PublicRestaurantController::class, 'dietaryTags']);
    Route::get('restaurants/{slug}/menu-items/{menuItem}', [PublicRestaurantController::class, 'menuItem'])
        ->scopeBindings();
    Route::get('restaurants/{slug}/availability', [PublicRestaurantController::class, 'availability']);

    // ---------- Authentication ----------
    Route::post('auth/register', [AuthController::class, 'register'])
        ->middleware('throttle:6,1');
    Route::post('auth/login', [AuthController::class, 'login'])
        ->middleware('throttle:10,1');

    // ---------- Authenticated customers ----------
    Route::middleware('auth:sanctum')->group(function (): void {
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::get('auth/me', [AuthController::class, 'me']);
        Route::patch('auth/me', [AuthController::class, 'updateProfile']);
        Route::put('auth/me/food-restrictions', [AuthController::class, 'updateFoodRestrictions']);

        Route::get('orders', [OrderController::class, 'index']);
        Route::post('orders', [OrderController::class, 'store'])->middleware('throttle:20,1');
        Route::get('orders/{order}', [OrderController::class, 'show']);
        Route::post('orders/{order}/cancel', [OrderController::class, 'cancel']);

        Route::get('reservations', [ReservationController::class, 'index']);
        Route::post('reservations', [ReservationController::class, 'store'])->middleware('throttle:10,1');
        Route::get('reservations/{reservation}', [ReservationController::class, 'show']);
        Route::post('reservations/{reservation}/cancel', [ReservationController::class, 'cancel']);
    });

    // ---------- Restaurant administration ----------
    Route::middleware('auth:sanctum')
        ->prefix('admin')
        ->group(function (): void {
            // Platform-level: only administrators (policy enforces this).
            Route::get('restaurants', [Admin\RestaurantController::class, 'index']);
            Route::post('restaurants', [Admin\RestaurantController::class, 'store']);

            // Payments are authorized from the payment's own order, so they are
            // not nested under a restaurant the client could point elsewhere.
            Route::post('payments/{payment}/refund', [Admin\PaymentController::class, 'refund']);

            // Platform accounts. The policy restricts every action to a Super
            // Admin; role assignment is additionally guarded by the user policy.
            Route::get('users', [Admin\UserController::class, 'index']);
            Route::patch('users/{user}/role', [Admin\UserController::class, 'updateRole']);

            Route::prefix('restaurants/{restaurant}')->group(function (): void {
                // Platform-level mutations.
                Route::patch('/', [Admin\RestaurantController::class, 'update']);
                Route::post('activate', [Admin\RestaurantController::class, 'activate']);
                Route::post('deactivate', [Admin\RestaurantController::class, 'deactivate']);

                // Restaurant-scoped (membership-aware).
                Route::get('settings', [Admin\RestaurantController::class, 'settings'])
                    ->middleware('restaurant.member');

                Route::middleware('restaurant.member')->group(function (): void {
                    Route::get('members', [Admin\MemberController::class, 'index']);
                    Route::post('members', [Admin\MemberController::class, 'store']);
                    Route::patch('members/{member}', [Admin\MemberController::class, 'update']);
                    Route::delete('members/{member}', [Admin\MemberController::class, 'destroy']);

                    Route::get('categories', [Admin\CategoryController::class, 'index']);
                    Route::post('categories', [Admin\CategoryController::class, 'store']);
                    Route::patch('categories/{category}', [Admin\CategoryController::class, 'update']);
                    Route::delete('categories/{category}', [Admin\CategoryController::class, 'destroy']);

                    Route::get('menu-items', [Admin\MenuItemController::class, 'index']);
                    Route::post('menu-items/image', [Admin\MenuItemController::class, 'uploadImage']);
                    Route::post('menu-items', [Admin\MenuItemController::class, 'store']);
                    Route::get('menu-items/{menuItem}', [Admin\MenuItemController::class, 'show']);
                    Route::patch('menu-items/{menuItem}', [Admin\MenuItemController::class, 'update']);
                    Route::patch('menu-items/{menuItem}/availability', [Admin\MenuItemController::class, 'updateAvailability']);
                    Route::delete('menu-items/{menuItem}', [Admin\MenuItemController::class, 'destroy']);

                    Route::get('tables', [Admin\DiningTableController::class, 'index']);
                    Route::post('tables', [Admin\DiningTableController::class, 'store']);
                    Route::patch('tables/{table}', [Admin\DiningTableController::class, 'update']);
                    Route::delete('tables/{table}', [Admin\DiningTableController::class, 'destroy']);

                    Route::get('orders', [Admin\OrderController::class, 'index']);
                    Route::get('orders/{order}', [Admin\OrderController::class, 'show']);
                    Route::patch('orders/{order}/status', [Admin\OrderController::class, 'updateStatus']);
                    Route::get('orders/{order}/payments', [Admin\PaymentController::class, 'index']);
                    Route::post('orders/{order}/payments', [Admin\PaymentController::class, 'store']);

                    Route::get('reservations', [Admin\ReservationController::class, 'index']);
                    Route::get('reservations/{reservation}', [Admin\ReservationController::class, 'show']);
                    Route::patch('reservations/{reservation}/status', [Admin\ReservationController::class, 'updateStatus']);

                    Route::get('deliveries', [Admin\DeliveryController::class, 'index']);
                    Route::get('deliveries/{delivery}', [Admin\DeliveryController::class, 'show']);
                    Route::patch('deliveries/{delivery}', [Admin\DeliveryController::class, 'update']);

                    Route::get('kpis', [Admin\KpiController::class, 'show']);
                });
            });
        });
});
