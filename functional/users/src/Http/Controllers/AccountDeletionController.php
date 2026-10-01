<?php

namespace Functional\Users\Http\Controllers;

use Functional\Users\Actions\RequestAccountDeletion;
use Functional\Users\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Feature 004 — what the deletion screen needs, and the request itself. Outside lomkit, like
 * the other account routes: it re-checks the password and closes the sessions (research R11).
 */
class AccountDeletionController
{
    public function show(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return new JsonResponse([
            'can_request' => true,
            'blocked_reason' => null,
            'erase_on' => $user->eraseOn()->toDateString(),
        ]);
    }

    public function store(Request $request, RequestAccountDeletion $requestAccountDeletion): JsonResponse
    {
        $validated = $request->validate([
            'password' => ['required', 'string'],
            'keep_published_subjects' => ['nullable', 'boolean'],
        ]);

        /** @var User $user */
        $user = $request->user();

        $requestAccountDeletion($user, $validated['password'], $validated['keep_published_subjects'] ?? null);

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return new JsonResponse(['erase_on' => $user->eraseOn()->toDateString()]);
    }
}
