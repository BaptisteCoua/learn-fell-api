<?php

namespace Functional\Users\Providers;

use Illuminate\Support\ServiceProvider;

/**
 * Wires Fortify's account flows (registration, login, verification, password reset)
 * to the users layer. Actions and limiters are registered in boot().
 */
class FortifyServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        //
    }
}
