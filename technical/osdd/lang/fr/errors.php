<?php

/*
| Messages of the business rule codes returned by the API as { "code", "message" }.
| Each layer adds its own codes here, next to the contract in specs/001-learning-content.
*/

return [
    'invalid_credentials' => 'Adresse email ou mot de passe incorrect.',
    'email_not_verified' => 'Votre adresse email n’est pas encore confirmée. Ouvrez le lien reçu par email pour activer votre compte.',
    'locked' => 'Trop de tentatives de connexion. Réessayez dans :minutes minutes, ou réinitialisez votre mot de passe.',
    'email_taken' => 'Un compte existe déjà avec cette adresse. Connectez-vous, ou réinitialisez votre mot de passe si vous l’avez oublié.',
    'email_pending_verification' => 'Un compte attend déjà la confirmation de cette adresse. Demandez un nouveau lien de confirmation.',
    'link_expired' => 'Ce lien a expiré ou a déjà été utilisé. Demandez-en un nouveau.',
    'category_name_taken' => 'Une catégorie « :name » existe déjà.',
    'category_not_empty' => 'Impossible de supprimer « :name » : :count sujets y sont rangés. Une catégorie doit être vide pour être supprimée.',
    'subject_has_no_question' => 'Ajoutez au moins une question avant de publier ce sujet.',
    'subject_retired' => 'Ce sujet a été retiré par la modération : seul un administrateur peut le rétablir.',
    'question_limit_reached' => 'Un sujet compte au plus :max questions.',
    'last_question_of_published_subject' => 'Un sujet publié doit garder au moins une question. Dépubliez-le d’abord pour supprimer celle-ci.',
    'reason_required' => 'Saisissez un motif : il sera visible par l’auteur.',
    'subject_not_retired' => 'Seul un sujet retiré peut être rétabli.',
    'report_already_pending' => 'Vous avez déjà signalé ce sujet. Votre signalement est en cours d’examen.',
    'subject_not_published' => 'Seul un sujet publié peut être appris.',
    'already_learning' => 'Vous apprenez déjà ce sujet.',
    'card_not_due' => 'Cette carte n’est pas à réviser aujourd’hui.',
    'search_too_short' => 'Saisissez au moins 2 caractères pour lancer la recherche.',
    'invalid_send_time' => 'Choisissez une heure entre 6 h et 23 h 30, à l’heure pleine ou à la demi-heure.',
    'invalid_push_subscription' => 'Cet appareil n’a pas pu être enregistré pour les notifications.',
    'invalid_link' => 'Ce lien n’est pas valide.',
    'image_invalid_format' => 'Choisissez une image au format JPEG, PNG ou WebP.',
    'recto_empty' => 'Ajoutez un texte ou une image au recto.',
    'recto_image_limit' => 'Un recto porte au plus :max images.',
    'subject_choice_required' => 'Choisissez ce que deviennent vos sujets publiés : les laisser sans votre nom, ou tout effacer.',
    'subject_authorless' => 'Ce sujet a été laissé à la communauté par un compte supprimé : il ne peut plus être modifié, seulement retiré par la modération.',
    'subject_withheld' => 'Ce sujet est retenu pendant la suppression du compte de son auteur : il ne peut pas être modifié.',
    'last_admin' => 'Vous êtes le dernier compte d’administration de CINQ : votre compte ne peut pas être supprimé tant qu’aucun autre administrateur n’est nommé.',
];
