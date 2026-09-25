<?php

namespace Functional\Users\Actions;

use Functional\Users\Models\User;
use Functional\Users\Support\AccountLockout;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Technical\Osdd\Exceptions\BusinessRuleException;

/**
 * Checks a login (FR-003, FR-006): a wrong email and a wrong password get the same answer,
 * and an unconfirmed account is only reported once its password is right.
 */
class AuthenticateUser
{
    public function __construct(private readonly AccountLockout $lockout) {}

    public function __invoke(Request $request): User
    {
        $email = mb_strtolower($request->string('email')->toString());
        $user = User::query()->where('email', $email)->first();

        if ($user === null || ! Hash::check($request->string('password')->toString(), $user->password)) {
            $this->lockout->recordFailure($email);

            throw new BusinessRuleException('invalid_credentials');
        }

        if (! $user->hasVerifiedEmail()) {
            throw new BusinessRuleException('email_not_verified');
        }

        $this->lockout->clear($email);
        $this->rememberTimezone($user, $request);

        return $user;
    }

    /**
     * Reviews fall due at midnight in the learner's own time zone, taken from the device.
     */
    private function rememberTimezone(User $user, Request $request): void
    {
        $timezone = $request->input('timezone');

        if (Validator::make(['timezone' => $timezone], ['timezone' => ['required', 'timezone:all_with_bc']])->passes()) {
            $user->update(['timezone' => $timezone]);
        }
    }
}
