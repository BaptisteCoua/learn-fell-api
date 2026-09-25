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
    'report_already_pending' => 'Vous avez déjà signalé ce sujet. Votre signalement est en cours d’examen.',
    'already_learning' => 'Vous apprenez déjà ce sujet.',
    'card_not_due' => 'Cette carte n’est pas à réviser aujourd’hui.',
    'search_too_short' => 'Saisissez au moins 2 caractères pour lancer la recherche.',
];
