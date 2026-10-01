<?php

/*
| Importing questions (specs/008-question-import): the messages of the preview, the headers it
| recognises and the content of the template workbook.
*/

return [
    'row' => [
        'recto_empty' => 'Le recto est vide.',
        'verso_empty' => 'Le verso est vide.',
        'recto_too_long' => 'Le recto dépasse 5 000 caractères.',
        'verso_too_long' => 'Le verso dépasse 5 000 caractères.',
    ],

    'warning' => [
        'duplicate_in_subject' => 'Ce recto existe déjà dans le sujet (question :position).',
        'duplicate_in_source' => 'Ce recto est le même qu’à la ligne :line.',
    ],

    'notice' => [
        'extra_columns_ignored' => 'Seules les deux premières colonnes sont lues : les suivantes sont ignorées.',
        'first_sheet_only' => 'Seule la première feuille du classeur est lue.',
        'header_ignored' => 'La première ligne est un en-tête : elle n’est pas importée.',
    ],

    /*
    | First lines read as a header rather than a question, compared without case nor accents.
    */
    'headers' => [
        ['recto', 'verso'],
        ['question', 'réponse'],
    ],

    'template' => [
        'file_name' => 'modele-questions.xlsx',
        'recto' => 'Recto',
        'verso' => 'Verso',
        'examples' => [
            ['Quelle est la capitale du Pérou ?', 'Lima'],
            ['Le passé de **go** ?', "**went**, comme dans :\n- I went home"],
        ],
    ],
];
