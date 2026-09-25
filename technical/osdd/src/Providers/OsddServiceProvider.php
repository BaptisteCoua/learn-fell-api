<?php

namespace Technical\Osdd\Providers;

use Xefi\LaravelOSDD\LayerServiceProvider;

class OsddServiceProvider extends LayerServiceProvider
{
    public function register(): void
    {
        $this->overrideConfigFrom(__DIR__.'/../../config/osdd.php', 'osdd');
        $this->overrideConfigFrom(__DIR__.'/../../config/cors.php', 'cors');

        $this->app->useLangPath(__DIR__.'/../../lang');
    }
}
