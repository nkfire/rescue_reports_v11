<?php
$EM_CONF[$_EXTKEY] = [
    'title' => 'rescue_reports',
    'description' => 'Detaillierte Einsatzberichte für Feuerwehren und BOS',
    'category' => 'plugin',
    'author' => 'Dein Name',
    'author_email' => 'dein@example.com',
    'state' => 'stable',
    'clearCacheOnLoad' => 1,
    'version' => '1.0.0',
    'constraints' => [
        'depends' => [
            'typo3' => '11.5.0-12.9.99',
            'php' => '7.4.0-8.2.99'
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
];