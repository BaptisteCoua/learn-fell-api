<?php

namespace Functional\Users\Http\Controllers;

use Functional\Users\Actions\RegisterAccount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Feature 005 — every valid registration gets the same answer, whatever the address, and nobody
 * is logged in by it: an account only opens a session once its address is confirmed.
 */
class RegisterController
{
    public function __invoke(Request $request, RegisterAccount $registerAccount): JsonResponse
    {
        $registerAccount($request->all());

        return new JsonResponse(['message' => __('users::account.registered')], 201);
    }
}
