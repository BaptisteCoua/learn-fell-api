<?php

namespace Functional\Users\Http\Responses;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Fortify\Contracts\RegisterResponse;

/**
 * Registration answers 201 without a session: Fortify logs the new account in, and an
 * unconfirmed account must not stay logged in (FR-002).
 */
class RegisteredResponse implements RegisterResponse
{
    /**
     * @param  Request  $request
     */
    public function toResponse($request): JsonResponse
    {
        Auth::guard('web')->logout();

        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return new JsonResponse(['message' => __('users::account.registered')], 201);
    }
}
