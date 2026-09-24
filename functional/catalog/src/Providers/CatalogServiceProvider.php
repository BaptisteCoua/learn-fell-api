<?php

namespace Functional\Catalog\Providers;

use Functional\Catalog\Database\Seeders\CatalogSeeder;
use Xefi\LaravelOSDD\LayerServiceProvider;

class CatalogServiceProvider extends LayerServiceProvider
{
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
            $this->loadSeeders([CatalogSeeder::class], priority: 10);
        }

        $this->withRouting(
            api: __DIR__.'/../../routes/api.php',
            commands: __DIR__.'/../../routes/console.php',
        );
    }

    public function register(): void
    {
        $this->overrideConfigFrom(__DIR__.'/../../config/purify.php', 'purify');
    }
}
