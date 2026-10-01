<?php

namespace Functional\Users\Http\Responses;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Laravel\Fortify\Fortify;
use Symfony\Component\HttpFoundation\Response;

/**
 * Fortify's login answer, plus `deletion_cancelled` when this login has just cancelled a
 * deletion request (feature 004, FR-014), so the web app can say so.
 */
class LoginResponse implements LoginResponseContract
{
    public const DELETION_CANCELLED = 'users.deletion_cancelled';

    /**
     * @param  Request  $request
     */
    public function toResponse($request): Response
    {
        if (! $request->wantsJson()) {
            return redirect()->intended(Fortify::redirects('login'));
        }

        return response()->json([
            'two_factor' => false,
            ...(Context::getHidden(self::DELETION_CANCELLED) === true ? ['deletion_cancelled' => true] : []),
        ]);
    }
}
