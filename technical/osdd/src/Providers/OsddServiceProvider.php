<?php

namespace Technical\Osdd\Providers;

use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Bridge\Brevo\Transport\BrevoTransportFactory;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Xefi\LaravelOSDD\LayerServiceProvider;

class OsddServiceProvider extends LayerServiceProvider
{
    public function register(): void
    {
        $this->overrideConfigFrom(__DIR__.'/../../config/osdd.php', 'osdd');
        $this->overrideConfigFrom(__DIR__.'/../../config/cors.php', 'cors');
        $this->overrideConfigFrom(__DIR__.'/../../config/mail.php', 'mail');

        $this->app->useLangPath(__DIR__.'/../../lang');
    }

    public function boot(): void
    {
        $this->registerBrevoMailTransport();
    }

    /**
     * Teach the mail manager the "brevo" transport so MAIL_MAILER=brevo sends
     * through Brevo's HTTP API — the deployment target (Railway) blocks the
     * SMTP ports that the default smtp transport would use.
     */
    private function registerBrevoMailTransport(): void
    {
        Mail::extend('brevo', function (array $config): TransportInterface {
            return (new BrevoTransportFactory())->create(
                new Dsn('brevo+api', 'default', $config['key'] ?? ''),
            );
        });
    }
}
