<?php

namespace Functional\Users\Actions;

use Closure;
use Functional\Users\Support\AccountLockout;
use Illuminate\Http\Request;

/**
 * Login pipeline step: refuses a locked account before its password is even checked.
 */
class EnsureAccountIsNotLocked
{
    public function __construct(private readonly AccountLockout $lockout) {}

    public function handle(Request $request, Closure $next): mixed
    {
        $this->lockout->ensureNotLocked($request->string('email')->toString());

        return $next($request);
    }
}
