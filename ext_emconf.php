<?php
$EM_CONF[$_EXTKEY] = [
    'title' => 'rescue_reports',
    'description' => 'Detaillierte Einsatzberichte für Feuerwehren und BOS',
    'category' => 'plugin',
    'author' => 'Norbert Külz',
    'author_email' => 'sportline105@googlemail.com',
    'state' => 'beta',
    'clearCacheOnLoad' => 1,
    'version' => '0.12.0',
    'constraints' => [
        'depends' => [
            'typo3' => '11.5.0-11.9.99',
            'php' => '7.4.0-8.2.99'
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
];