<?php

return [
    'mailers' => [
        /*
         * Brevo over its HTTP API (port 443). Railway blocks outbound SMTP
         * (ports 25/465/587), so the SMTP transport cannot reach
         * smtp-relay.brevo.com from a deployed service; the API transport,
         * registered as the "brevo" scheme in OsddServiceProvider, sends over
         * HTTPS instead. Select it with MAIL_MAILER=brevo and BREVO_API_KEY.
         */
        'brevo' => [
            'transport' => 'brevo',
            'key' => env('BREVO_API_KEY'),
        ],
    ],
];
