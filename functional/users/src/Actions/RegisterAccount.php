<?php

namespace Functional\Users\Actions;

use Functional\Users\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

/**
 * Registration (feature 001, FR-001), silent about existing accounts (feature 005): the input is
 * checked before anything is read, then a free address gets its inactive account, an account
 * still waiting for its confirmation gets a new link, and a confirmed account gets nothing.
 */
class RegisterAccount
{
    public const DEFAULT_TIMEZONE = 'Europe/Paris';

    public const LINK_RESEND_SECONDS = 60;

    /**
     * @param  array<string, mixed>  $input
     */
    public function __invoke(array $input): void
    {
        $validated = Validator::make($input, [
            'display_name' => ['required', 'string', 'min:2', 'max:60'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string', Password::min(8), 'confirmed'],
            'timezone' => ['nullable', 'string', 'timezone:all_with_bc'],
        ])->validate();

        $existingUser = User::query()->where('email', mb_strtolower(trim($validated['email'])))->first();

        if ($existingUser === null) {
            $this->createAccount($validated);

            return;
        }

        if (! $existingUser->hasVerifiedEmail()) {
            $this->sendLinkAgain($existingUser);
        }
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function createAccount(array $validated): void
    {
        $user = User::query()->create([
            'display_name' => $validated['display_name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
            'timezone' => $validated['timezone'] ?? self::DEFAULT_TIMEZONE,
        ]);

        event(new Registered($user));
    }

    /**
     * A new link replaces the previous one; the account keeps everything else, including its
     * creation date, so it is still removed 7 days after it (FR-004). One link a minute at most,
     * counted per account so that no address can flood its owner's mailbox (FR-005).
     */
    private function sendLinkAgain(User $user): void
    {
        RateLimiter::attempt(
            'registration-link:'.$user->getKey(),
            1,
            fn () => $user->sendEmailVerificationNotification(),
            self::LINK_RESEND_SECONDS,
        );
    }
}
