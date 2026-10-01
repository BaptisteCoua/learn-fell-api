<?php

namespace Functional\Users\Http\Controllers;

use Functional\Users\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The signed-in account as the web app needs it: its name, email, permissions, and the time zone
 * its review days are counted in, also offline (feature 006).
 */
class CurrentUserController
{
    public function __invoke(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return new JsonResponse([
            'id' => $user->getKey(),
            'display_name' => $user->display_name,
            'email' => $user->email,
            'permissions' => $user->getAllPermissions()->pluck('name')->values(),
            'timezone' => $user->timezone,
        ]);
    }
}
