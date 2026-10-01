<?php

namespace Functional\Users\Http\Controllers;

use Functional\Users\Actions\RequestAccountDeletion;
use Functional\Users\Models\User;
use Functional\Users\Support\LastAdministratorGuard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Feature 004 — what the deletion screen needs, and the request itself. Outside lomkit, like
 * the other account routes: it re-checks the password and closes the sessions (research R11).
 */
class AccountDeletionController
{
    public function show(Request $request, LastAdministratorGuard $lastAdministratorGuard): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $isLastAdministrator = $lastAdministratorGuard->isLastAdministrator($user);

        return new JsonResponse([
            'can_request' => ! $isLastAdministrator,
            'blocked_reason' => $isLastAdministrator ? 'last_admin' : null,
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
        // auth:sanctum made its guard the default one, which the database session handler reads
        // to stamp the session it writes at the end of this request: forget the user there too.
        Auth::forgetUser();

        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return new JsonResponse(['erase_on' => $user->eraseOn()->toDateString()]);
    }
}
