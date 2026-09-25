<?php

return [
    'accepted' => 'Le champ :attribute doit être accepté.',
    'array' => 'Le champ :attribute doit être une liste.',
    'boolean' => 'Le champ :attribute doit être vrai ou faux.',
    'confirmed' => 'Les deux saisies du champ :attribute ne correspondent pas.',
    'date' => 'Le champ :attribute doit être une date valide.',
    'distinct' => 'Le champ :attribute contient une valeur en double.',
    'email' => 'Le champ :attribute doit être une adresse email valide.',
    'enum' => 'La valeur du champ :attribute n’est pas valide.',
    'exists' => 'La valeur du champ :attribute n’existe pas.',
    'in' => 'La valeur du champ :attribute n’est pas valide.',
    'integer' => 'Le champ :attribute doit être un nombre entier.',
    'max' => [
        'array' => 'Le champ :attribute ne peut pas contenir plus de :max éléments.',
        'numeric' => 'Le champ :attribute ne peut pas dépasser :max.',
        'string' => 'Le champ :attribute ne peut pas dépasser :max caractères.',
    ],
    'min' => [
        'array' => 'Le champ :attribute doit contenir au moins :min éléments.',
        'numeric' => 'Le champ :attribute doit être au moins :min.',
        'string' => 'Le champ :attribute doit contenir au moins :min caractères.',
    ],
    'numeric' => 'Le champ :attribute doit être un nombre.',
    'present' => 'Le champ :attribute doit être présent.',
    'present_if' => 'Le champ :attribute doit être présent quand :other vaut :value.',
    'prohibited' => 'Le champ :attribute ne peut pas être envoyé.',
    'prohibited_if' => 'Le champ :attribute ne peut pas être envoyé quand :other vaut :value.',
    'prohibited_unless' => 'Le champ :attribute ne peut être envoyé que si :other vaut :values.',
    'password' => [
        'letters' => 'Le champ :attribute doit contenir au moins une lettre.',
        'mixed' => 'Le champ :attribute doit contenir au moins une majuscule et une minuscule.',
        'numbers' => 'Le champ :attribute doit contenir au moins un chiffre.',
        'symbols' => 'Le champ :attribute doit contenir au moins un symbole.',
        'uncompromised' => 'Ce mot de passe est apparu dans une fuite de données. Choisissez-en un autre.',
    ],
    'required' => 'Le champ :attribute est obligatoire.',
    'string' => 'Le champ :attribute doit être un texte.',
    'timezone' => 'Le champ :attribute doit être un fuseau horaire valide.',
    'unique' => 'Cette valeur du champ :attribute est déjà utilisée.',

    // lomkit reports a mutation field as mutate.<n>.attributes.<field>: show the field name.
    'attributes' => (static function (): array {
        $attributes = [
            'display_name' => 'nom affiché',
            'email' => 'adresse email',
            'password' => 'mot de passe',
            'timezone' => 'fuseau horaire',
            'title' => 'titre',
            'description' => 'description',
            'category_id' => 'catégorie',
            'tags' => 'tags',
            'recto_html' => 'recto',
            'verso_html' => 'verso',
            'name' => 'nom',
            'reason' => 'motif',
            'comment' => 'commentaire',
            'q' => 'recherche',
        ];

        foreach ($attributes as $field => $name) {
            $attributes['mutate.*.attributes.'.$field] = $name;
        }

        return $attributes;
    })(),
];
