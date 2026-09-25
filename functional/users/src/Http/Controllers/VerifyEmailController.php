<?php

namespace Functional\Users\Http\Controllers;

use Functional\Users\Models\User;
use Functional\Users\Support\EmailVerificationLink;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Technical\Osdd\Exceptions\BusinessRuleException;

/**
 * Opens the confirmation link (FR-002): activates the account and logs it in. An expired,
 * already used or replaced link is refused with `link_expired`.
 */
class VerifyEmailController
{
    public function __invoke(Request $request, EmailVerificationLink $link, int $id, string $hash): Response
    {
        $user = User::query()->find($id);

        if (
            $user === null
            || $user->hasVerifiedEmail()
            || ! hash_equals($link->hash($user), $hash)
            || ! $request->hasValidRelativeSignature()
            || ! $link->consume($user, $request->string('nonce')->toString())
        ) {
            throw new BusinessRuleException('link_expired', 403);
        }

        $user->markEmailAsVerified();
        event(new Verified($user));

        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        return response()->noContent();
    }
}
