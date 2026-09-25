<?php

namespace Functional\Users\Http\Controllers;

use Functional\Users\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Sends a new confirmation link, which replaces the previous one. The answer is the same
 * whether the address has an account or not (FR-006).
 */
class ResendVerificationEmailController
{
    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate(['email' => ['required', 'string', 'email']]);

        $user = User::query()->where('email', mb_strtolower($validated['email']))->first();

        if ($user !== null && ! $user->hasVerifiedEmail()) {
            $user->sendEmailVerificationNotification();
        }

        return new JsonResponse(['message' => __('users::account.verification_link_sent')], 202);
    }
}
