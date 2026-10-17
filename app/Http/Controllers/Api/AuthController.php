<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Auth\LoginRequest;
use App\Http\Requests\Api\Auth\RegisterRequest;
use App\Http\Requests\Api\Auth\UpdateProfileRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
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
    public function register(RegisterRequest $request): JsonResponse
    {
        // Public registration always creates a CUSTOMER; role escalation is not
        // possible through this endpoint.
        $user = User::query()->create([
            ...$request->safe()->except(['password_confirmation']),
            'role' => UserRole::Customer,
        ]);

        $this->loginForSession($request, $user);

        return (new UserResource($user->load('memberships.restaurant')))
            ->response()
            ->setStatusCode(201);
    }

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

    public function logout(Request $request): Response
    {
        $this->guard()->logout();

        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return response()->noContent();
    }

    public function me(Request $request): UserResource
    {
        return new UserResource(
            $request->user()->load('memberships.restaurant'),
        );
    }

    public function updateProfile(UpdateProfileRequest $request): UserResource
    {
        $user = $request->user();

        $user->fill($request->validated());

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        return new UserResource($user->load('memberships.restaurant'));
    }

    private function loginForSession(Request $request, User $user, bool $remember = false): void
    {
        $this->guard()->login($user, $remember);

        if ($request->hasSession()) {
            $request->session()->regenerate();
        }
    }

    private function guard(): StatefulGuard
    {
        /** @var StatefulGuard $guard */
        $guard = auth('web');

        return $guard;
    }
}
