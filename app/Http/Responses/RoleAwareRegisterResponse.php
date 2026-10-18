<?php

namespace App\Http\Responses;

use App\Support\HomePath;
use Illuminate\Http\JsonResponse;
use Laravel\Fortify\Contracts\RegisterResponse as RegisterResponseContract;

class RoleAwareRegisterResponse implements RegisterResponseContract
{
    public function toResponse($request)
    {
        if ($request->wantsJson()) {
            return new JsonResponse('', 201);
        }

        return redirect()->intended(HomePath::forUser($request->user()));
    }
}
