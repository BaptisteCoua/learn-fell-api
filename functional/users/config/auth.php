<?php

/*
| Account links: email confirmation valid 24 hours (FR-002), password reset 60 minutes (FR-005).
*/

return [
    'verification' => [
        'expire' => 1440,
    ],
    'passwords' => [
        'users' => [
            'expire' => 60,
            'throttle' => 60,
        ],
    ],
];
