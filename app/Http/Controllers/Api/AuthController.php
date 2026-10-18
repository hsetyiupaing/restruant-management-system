<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Auth\LoginRequest;
use App\Http\Requests\Api\Auth\RegisterRequest;
use App\Http\Requests\Api\Auth\UpdateFoodRestrictionsRequest;
use App\Http\Requests\Api\Auth\UpdateProfileRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\FoodRestrictionService;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Register a customer account and sign it in on the current session.
     */
    /**
     * Create a new customer account and start an authenticated session for it.
     */
    public function register(RegisterRequest $request, FoodRestrictionService $restrictions): JsonResponse
    {
        // Public registration always creates a CUSTOMER; role escalation is not
        // possible through this endpoint.
        $user = User::query()->create([
            ...$request->safe()->except(['password_confirmation', 'food_restrictions']),
            'role' => UserRole::Customer,
        ]);

        $restrictions->replaceFor(
            $user,
            $request->foodRestrictionRows($request->validated('food_restrictions') ?? []),
        );

        $this->loginForSession($request, $user);

        return (new UserResource($user->load(['memberships.restaurant', 'foodRestrictions'])))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Authenticate an existing user and establish the browser session used by the SPA.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $throttleKey = Str::transliterate(Str::lower($request->string('email')).'|'.$request->ip());

        if (RateLimiter::tooManyAttempts('api-login:'.$throttleKey, 5)) {
            throw ValidationException::withMessages([
                'email' => __('Too many login attempts. Please try again in :seconds seconds.', [
                    'seconds' => RateLimiter::availableIn('api-login:'.$throttleKey),
                ]),
            ])->status(429);
        }

        $user = User::query()->where('email', $request->string('email'))->first();

        if ($user === null || ! Hash::check($request->string('password'), $user->password)) {
            RateLimiter::hit('api-login:'.$throttleKey, 60);

            throw ValidationException::withMessages([
                'email' => __('These credentials do not match our records.'),
            ]);
        }

        RateLimiter::clear('api-login:'.$throttleKey);

        $this->loginForSession($request, $user, $request->boolean('remember'));

        return (new UserResource($user->load('memberships.restaurant')))->response();
    }

    /**
     * End the active web session and invalidate the current browser authentication state.
     */
    public function logout(Request $request): Response
    {
        $this->guard()->logout();

        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return response()->noContent();
    }

    /**
     * Return the authenticated user with their restaurant memberships for the client UI.
     */
    public function me(Request $request): UserResource
    {
        return new UserResource(
            $request->user()->load(['memberships.restaurant', 'foodRestrictions']),
        );
    }

    /**
     * Update the signed-in user's profile and clear email verification if the address changes.
     */
    public function updateProfile(UpdateProfileRequest $request, FoodRestrictionService $restrictions): UserResource
    {
        $user = $request->user();

        $user->fill($request->safe()->except('food_restrictions'));

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        if ($request->has('food_restrictions')) {
            $restrictions->replaceFor(
                $user,
                $request->foodRestrictionRows($request->validated('food_restrictions') ?? []),
            );
        }

        return new UserResource($user->load(['memberships.restaurant', 'foodRestrictions']));
    }

    /**
     * Replace the signed-in customer's saved food restrictions.
     *
     * A separate endpoint from the profile so the restriction form never has
     * to re-send (and therefore re-validate) the display name and email.
     */
    public function updateFoodRestrictions(
        UpdateFoodRestrictionsRequest $request,
        FoodRestrictionService $restrictions,
    ): UserResource {
        $user = $request->user();

        $restrictions->replaceFor(
            $user,
            $request->foodRestrictionRows($request->validated('food_restrictions') ?? []),
        );

        return new UserResource($user->load(['memberships.restaurant', 'foodRestrictions']));
    }

    /**
     * Log a user into the configured web guard and refresh the session cookie for the app.
     */
    private function loginForSession(Request $request, User $user, bool $remember = false): void
    {
        $this->guard()->login($user, $remember);

        if ($request->hasSession()) {
            $request->session()->regenerate();
        }
    }

    /**
     * Resolve the session-based web guard used by the API's SPA authentication flow.
     */
    private function guard(): StatefulGuard
    {
        /** @var StatefulGuard $guard */
        $guard = auth('web');

        return $guard;
    }
}
