<?php

namespace Functional\Users\Providers;

use Functional\Users\Console\GrantAdminCommand;
use Functional\Users\Database\Seeders\UsersSeeder;
use Functional\Users\Models\User;
use Functional\Users\Policies\UserPolicy;
use Illuminate\Contracts\Foundation\CachesRoutes;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Xefi\LaravelOSDD\LayerServiceProvider;

class UsersServiceProvider extends LayerServiceProvider
{
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
            $this->loadSeeders([UsersSeeder::class]);
            $this->commands([GrantAdminCommand::class]);
        }

        Gate::policy(User::class, UserPolicy::class);

        $this->loadTranslationsFrom(__DIR__.'/../../lang', 'users');

        $this->withRouting(
            api: __DIR__.'/../../routes/api.php',
        );
        $this->withAccountRouting();
    }

    public function register(): void
    {
        config([
            'auth.providers.users.model' => User::class,
            // Logged in for 30 days on the device, without ticking anything (research R2).
            'session.lifetime' => 60 * 24 * 30,
            'session.expire_on_close' => false,
        ]);

        $this->overrideConfigFrom(__DIR__.'/../../config/auth.php', 'auth');

        $this->overrideConfigFrom(__DIR__.'/../../config/fortify.php', 'fortify');
        $this->overrideConfigFrom(__DIR__.'/../../config/sanctum.php', 'sanctum');
    }

    /**
     * The confirmation routes live beside Fortify's, with its `web` session and `/api` prefix.
     */
    private function withAccountRouting(): void
    {
        if ($this->app instanceof CachesRoutes && $this->app->routesAreCached()) {
            return;
        }

        Route::middleware('web')->prefix('api')->group(__DIR__.'/../../routes/account.php');
    }
}
