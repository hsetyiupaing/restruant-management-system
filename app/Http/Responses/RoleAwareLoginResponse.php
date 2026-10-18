<?php

namespace App\Http\Responses;

use App\Support\HomePath;
use Illuminate\Http\JsonResponse;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Laravel\Fortify\Fortify;

/**
 * Send each role to the part of the app it can actually use instead of the
 * fixed `fortify.home` path, which would 403 for a customer.
 */
class RoleAwareLoginResponse implements LoginResponseContract
{
    public function toResponse($request)
    {
        if ($request->wantsJson()) {
            return new JsonResponse(['two_factor' => false]);
        }

        return redirect()->intended(HomePath::forUser($request->user()));
    }
}
