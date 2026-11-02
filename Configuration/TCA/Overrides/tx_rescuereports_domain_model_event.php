<?php

defined('TYPO3') or die();

$tmpColumns = [
    'fahrzeugeinsatz' => [
        'exclude' => 1,
        'label' => 'Fahrzeugeinsatz',
        'config' => [
            'type' => 'user',
            'renderType' => 'eventVehicleAssignment',
        ],
    ],
];

\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addTCAcolumns(
    'tx_rescuereports_domain_model_event',
    $tmpColumns
);

// Hier wird das Feld eingebunden:
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addToAllTCAtypes(
    'tx_rescuereports_domain_model_event',
    'fahrzeugeinsatz',
    '',
    'after:title'
);
