<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'Powermail ↔ YAML',
    'description' => 'Powermail-Formulare als YAML-Datei: Datei als Quelle, Powermail arbeitet unverändert mit der Datenbank.',
    'category' => 'be',
    'state' => 'stable',
    'version' => '1.0.0',
    'constraints' => [
        'depends' => [
            'typo3' => '13.4.0-13.4.99',
            'powermail' => '13.0.0-13.99.99',
        ],
    ],
];
