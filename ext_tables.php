<?php
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

defined('TYPO3') or die();

$versionInformation = new Typo3Version();
if ($versionInformation->getMajorVersion() < 12) {
    ExtensionManagementUtility::allowTableOnStandardPages('tx_rescuereports_domain_model_event');
}

ExtensionManagementUtility::addPageTSConfig(
    "@import 'EXT:rescue_reports/Configuration/TSconfig/NewContentElementWizard.tsconfig'"
);