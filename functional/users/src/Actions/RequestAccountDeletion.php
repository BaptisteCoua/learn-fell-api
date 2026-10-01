<?php

namespace Functional\Users\Actions;

use Functional\Users\Events\AccountDeletionRequested;
use Functional\Users\Models\User;
use Functional\Users\Notifications\AccountDeletionRequestedNotification;
use Functional\Users\Support\LastAdministratorGuard;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Feature 004, FR-003 to FR-012 — records a deletion request confirmed by the password and
 * closes every session of the account. The other layers hide what they must on
 * AccountDeletionRequested, inside the same transaction: one of them refusing undoes it all.
 */
class RequestAccountDeletion
{
    public function __construct(private readonly LastAdministratorGuard $lastAdministratorGuard) {}

    public function __invoke(User $user, string $password, ?bool $keepsPublishedSubjects): void
    {
        $this->lastAdministratorGuard->ensureCanLeave($user);

        if (! Hash::check($password, $user->password)) {
            throw ValidationException::withMessages(['password' => __('users::account.wrong_password')]);
        }

        DB::transaction(function () use ($user, $keepsPublishedSubjects): void {
            $user->forceFill([
                'deletion_requested_at' => now(),
                'keeps_published_subjects' => $keepsPublishedSubjects,
            ])->save();

            DB::table('sessions')->where('user_id', $user->getKey())->delete();

            event(new AccountDeletionRequested($user));
        });

        $user->notify(new AccountDeletionRequestedNotification);
    }
}
