<?php

/*
| Images on the recto of a question (specs/003-question-images, research R2 to R4). Files are
| served by the layer's own route, never from a public disk.
*/

return [
    'images' => [
        'disk' => env('QUESTION_IMAGES_DISK', 'local'),
        'max_per_recto' => 4,
        'max_kilobytes' => 5120,
        'max_side' => 8000,
        'variant_widths' => [480, 960, 1600],
        'webp_quality' => 80,
        'pending_hours' => 24,
    ],
];
