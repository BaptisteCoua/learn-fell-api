<?php

namespace Functional\Users\Support;

use Illuminate\Support\Facades\RateLimiter;
use Technical\Osdd\Exceptions\BusinessRuleException;

/**
 * Locks an account for 15 minutes after 5 consecutive failed logins (FR-004). Counted per
 * account, not per address, so moving to another network does not reset it.
 */
class AccountLockout
{
    public const MAX_FAILURES = 5;

    public const LOCK_SECONDS = 900;

    public function ensureNotLocked(string $email): void
    {
        $lockKey = $this->lockKey($email);

        if (RateLimiter::attempts($lockKey) > 0) {
            $retryAfter = RateLimiter::availableIn($lockKey);

            throw new BusinessRuleException(
                'locked',
                429,
                ['minutes' => (int) ceil($retryAfter / 60)],
                ['retry_after' => $retryAfter],
            );
        }
    }

    public function recordFailure(string $email): void
    {
        $failures = RateLimiter::hit($this->failuresKey($email), self::LOCK_SECONDS);

        if ($failures >= self::MAX_FAILURES) {
            RateLimiter::clear($this->failuresKey($email));
            RateLimiter::hit($this->lockKey($email), self::LOCK_SECONDS);
        }
    }

    public function clear(string $email): void
    {
        RateLimiter::clear($this->failuresKey($email));
    }

    private function failuresKey(string $email): string
    {
        return 'login-failures:'.sha1(mb_strtolower($email));
    }

    private function lockKey(string $email): string
    {
        return 'login-lock:'.sha1(mb_strtolower($email));
    }
}
