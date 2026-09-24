<?php

namespace Functional\Learning\Providers;

use Xefi\LaravelOSDD\LayerServiceProvider;

class LearningServiceProvider extends LayerServiceProvider
{
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');
        }

        $this->withRouting(
            api: __DIR__ . '/../../routes/api.php',
            commands: __DIR__ . '/../../routes/console.php',
        );
    }

    public function register(): void
    {
        //
    }
}
