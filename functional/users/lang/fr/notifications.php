<?php

return [
    'greeting' => 'Bonjour :name,',
    'salutation' => 'À bientôt sur CINQ.',
    'verify_email' => [
        'subject' => 'Confirmez votre adresse email',
        'intro' => 'Pour activer votre compte CINQ, confirmez votre adresse email.',
        'action' => 'Confirmer mon adresse',
        'expiry' => 'Ce lien est valable 24 heures et ne sert qu’une fois.',
        'ignore' => 'Si vous n’avez pas créé de compte, ignorez cet email : le compte sera supprimé au bout de 7 jours.',
    ],
    'reset_password' => [
        'subject' => 'Réinitialisez votre mot de passe',
        'intro' => 'Vous avez demandé un nouveau mot de passe pour votre compte CINQ.',
        'action' => 'Choisir un nouveau mot de passe',
        'expiry' => 'Ce lien est valable :minutes minutes et ne sert qu’une fois.',
        'ignore' => 'Si vous n’avez rien demandé, ignorez cet email : votre mot de passe reste le même.',
    ],
];
