<?php

namespace Functional\Users\Providers;

use Functional\Users\Actions\AuthenticateUser;
use Functional\Users\Actions\CreateNewUser;
use Functional\Users\Actions\EnsureAccountIsNotLocked;
use Functional\Users\Actions\ResetUserPassword;
use Functional\Users\Http\Responses\FailedPasswordResetResponse;
use Functional\Users\Http\Responses\PasswordResetLinkResponse;
use Functional\Users\Http\Responses\PasswordResetResponse;
use Functional\Users\Http\Responses\RegisteredResponse;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laravel\Fortify\Actions\AttemptToAuthenticate;
use Laravel\Fortify\Actions\CanonicalizeUsername;
use Laravel\Fortify\Actions\PrepareAuthenticatedSession;
use Laravel\Fortify\Contracts\FailedPasswordResetLinkRequestResponse;
use Laravel\Fortify\Contracts\FailedPasswordResetResponse as FailedPasswordResetResponseContract;
use Laravel\Fortify\Contracts\PasswordResetResponse as PasswordResetResponseContract;
use Laravel\Fortify\Contracts\RegisterResponse;
use Laravel\Fortify\Contracts\SuccessfulPasswordResetLinkRequestResponse;
use Laravel\Fortify\Fortify;

/**
 * Wires Fortify's account flows (registration, login, password reset) to the users layer.
 * Email confirmation has its own routes (routes/account.php): Fortify's need a session.
 */
class FortifyServiceProvider extends ServiceProvider
{
    public const LOGIN_ATTEMPTS_PER_MINUTE_PER_IP = 30;

    public function boot(): void
    {
        // Bound in boot: Fortify registers its own responses after this provider's register().
        $this->app->singleton(RegisterResponse::class, RegisteredResponse::class);
        $this->app->singleton(SuccessfulPasswordResetLinkRequestResponse::class, PasswordResetLinkResponse::class);
        $this->app->singleton(FailedPasswordResetLinkRequestResponse::class, PasswordResetLinkResponse::class);
        $this->app->singleton(PasswordResetResponseContract::class, PasswordResetResponse::class);
        $this->app->singleton(FailedPasswordResetResponseContract::class, FailedPasswordResetResponse::class);

        Fortify::createUsersUsing(CreateNewUser::class);
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        Fortify::authenticateUsing(fn (Request $request) => app(AuthenticateUser::class)($request));
        Fortify::authenticateThrough(fn () => [
            CanonicalizeUsername::class,
            EnsureAccountIsNotLocked::class,
            AttemptToAuthenticate::class,
            PrepareAuthenticatedSession::class,
        ]);

        // The per-account lock (FR-004) is AccountLockout; this only slows down one address
        // trying many accounts.
        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(self::LOGIN_ATTEMPTS_PER_MINUTE_PER_IP)->by($request->ip()));
    }
}
