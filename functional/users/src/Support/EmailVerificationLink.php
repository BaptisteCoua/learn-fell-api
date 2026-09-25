<?php

namespace Functional\Users\Support;

use Functional\Users\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * The confirmation link sent by email (FR-002): signed, valid 24 hours, and single use. Each
 * link carries a nonce kept until it is used; sending a new link replaces the nonce, which
 * invalidates the previous one.
 */
class EmailVerificationLink
{
    public const ROUTE = 'users.verification.verify';

    /**
     * The web page that opens the link and calls the API with its signed parameters.
     */
    public function issue(User $user): string
    {
        $validMinutes = (int) config('auth.verification.expire');
        $nonce = Str::random(40);

        Cache::put($this->nonceKey($user), $nonce, now()->addMinutes($validMinutes));

        $signedPath = URL::temporarySignedRoute(
            self::ROUTE,
            now()->addMinutes($validMinutes),
            ['id' => $user->getKey(), 'hash' => $this->hash($user), 'nonce' => $nonce],
            absolute: false,
        );
        parse_str((string) parse_url($signedPath, PHP_URL_QUERY), $signedQuery);

        return config('app.frontend_url').'/inscription/confirmation?'.http_build_query([
            'id' => $user->getKey(),
            'hash' => $this->hash($user),
            ...$signedQuery,
        ]);
    }

    /**
     * Uses up the link: true only once, for the latest link sent.
     */
    public function consume(User $user, string $nonce): bool
    {
        $expectedNonce = Cache::get($this->nonceKey($user));

        if (! is_string($expectedNonce) || ! hash_equals($expectedNonce, $nonce)) {
            return false;
        }

        Cache::forget($this->nonceKey($user));

        return true;
    }

    public function hash(User $user): string
    {
        return sha1($user->getEmailForVerification());
    }

    private function nonceKey(User $user): string
    {
        return 'email-verification-nonce:'.$user->getKey();
    }
}
