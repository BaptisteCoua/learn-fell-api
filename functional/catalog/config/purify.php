<?php

/*
| Rich text of questions (research R4): only the formatting the editor produces. Links keep
| http(s) and mailto only; `rel` is added by the SanitizedHtml cast.
*/

return [
    'default' => 'default',
    'configs' => [
        'default' => [
            'Core.Encoding' => 'utf-8',
            'HTML.Doctype' => 'HTML 4.01 Transitional',
            'HTML.Allowed' => 'p,br,strong,em,ul,ol,li,code,pre,a[href]',
            'HTML.ForbiddenElements' => '',
            'URI.AllowedSchemes' => ['http' => true, 'https' => true, 'mailto' => true],
            'CSS.AllowedProperties' => '',
            'AutoFormat.AutoParagraph' => false,
            'AutoFormat.RemoveEmpty' => false,
        ],
    ],
];
