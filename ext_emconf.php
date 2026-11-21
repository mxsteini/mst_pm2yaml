<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'Powermail ↔ YAML',
    'description' => 'Powermail-Formulare als YAML-Datei: Datei als Quelle, Powermail arbeitet unverändert mit der Datenbank.',
    'category' => 'be',
    'author' => 'Michael Stein (mxsteini)',
    'author_email' => 'info@michaelstein-itb.de',
    'author_company' => 'Michael Stein IT-Beratung',
    'state' => 'stable',
    'version' => '1.0.0',
    'constraints' => [
        'depends' => [
            'php' => '8.2.0-8.5.99',
            'typo3' => '13.4.0-13.4.99',
            'powermail' => '13.0.0-13.99.99',
        ],
    ],
];
