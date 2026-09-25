<?php

namespace Functional\Users\Actions;

use Functional\Users\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Laravel\Fortify\Contracts\CreatesNewUsers;
use Technical\Osdd\Exceptions\BusinessRuleException;

/**
 * Registration (FR-001): the account starts inactive until its email is confirmed.
 */
class CreateNewUser implements CreatesNewUsers
{
    public const DEFAULT_TIMEZONE = 'Europe/Paris';

    /**
     * @param  array<string, mixed>  $input
     */
    public function create(array $input): User
    {
        $validated = Validator::make($input, [
            'display_name' => ['required', 'string', 'min:2', 'max:60'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string', Password::min(8), 'confirmed'],
            'timezone' => ['nullable', 'string', 'timezone:all_with_bc'],
        ])->validate();

        $this->ensureEmailIsFree($validated['email']);

        return User::query()->create([
            'display_name' => $validated['display_name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
            'timezone' => $validated['timezone'] ?? self::DEFAULT_TIMEZONE,
        ]);
    }

    /**
     * A taken address says so (scenario 6): log in or reset the password for an active account,
     * receive the confirmation link again for one still waiting.
     */
    private function ensureEmailIsFree(string $email): void
    {
        $existingUser = User::query()->where('email', mb_strtolower($email))->first();

        if ($existingUser === null) {
            return;
        }

        throw new BusinessRuleException(
            $existingUser->hasVerifiedEmail() ? 'email_taken' : 'email_pending_verification',
        );
    }
}
