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
    'account_deletion' => [
        'subject' => 'Votre compte CINQ sera supprimé le :date',
        'intro' => 'Vous avez demandé la suppression de votre compte CINQ. Il est désactivé, et il sera effacé définitivement le :date.',
        'everything_erased' => 'Seront effacés : votre nom, votre adresse email, votre progression, vos réglages de rappels et tous vos sujets.',
        'subjects_kept' => 'Seront effacés : votre nom, votre adresse email, votre progression, vos réglages de rappels et vos sujets non publiés. Vos sujets publiés restent au catalogue, signés « Auteur supprimé ».',
        'moderation_kept' => 'Vos signalements et vos décisions de modération sont conservés, sans votre nom.',
        'cancel' => 'Vous avez changé d’avis ? Il vous suffit de vous reconnecter avant le :date : tout sera rétabli.',
        'action' => 'Me reconnecter',
    ],
];
