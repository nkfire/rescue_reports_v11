<?php
// === Configuration/Backend/Module.php ===
use TYPO3\CMS\Extbase\Utility\ExtensionUtility;

ExtensionUtility::registerModule(
    'Nkfire.RescueReports',
    'tools',
    'migration',
    '',
    [
        \Nkfire\RescueReports\Controller\Backend\MigrationController::class => 'index,setup,run,resetConfirm,reset',
    ],
    [
        'access' => 'admin',
        'icon' => 'EXT:rescue_reports/Resources/Public/Icons/module-migration.svg',
        'labels' => 'LLL:EXT:rescue_reports/Resources/Private/Language/locallang_mod.xlf',
    ]
);

// Eigene Backend-CSS einbinden
$GLOBALS['TYPO3_CONF_VARS']['BE']['customCssFiles'][] = 'EXT:rescue_reports/Resources/Public/Css/backend.css';