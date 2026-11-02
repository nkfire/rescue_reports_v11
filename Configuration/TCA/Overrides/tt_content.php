<?php
declare(strict_types=1);

defined('TYPO3') or die();

use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Extbase\Utility\ExtensionUtility;

(function (): void {

    // Hauptplugin
    ExtensionUtility::registerPlugin(
        'RescueReports',
        'Eventlist',
        'Rescue Reports: Einsatzübersicht',
        'rescue_reports_eventlist'
    );

    $pluginSignature = 'rescuereports_eventlist';
    $GLOBALS['TCA']['tt_content']['types']['list']['subtypes_addlist'][$pluginSignature] = 'pi_flexform';
    ExtensionManagementUtility::addPiFlexFormValue(
        $pluginSignature,
        'FILE:EXT:rescue_reports/Configuration/FlexForms/eventlist.xml'
    );

    // Statistik-Plugin
    ExtensionUtility::registerPlugin(
        'RescueReports',
        'Statistics',
        'Rescue Reports: Jahresstatistik',
        'rescue_reports_statistics'
    );

    $pluginSignatureStats = 'rescuereports_statistics';
    $GLOBALS['TCA']['tt_content']['types']['list']['subtypes_addlist'][$pluginSignatureStats] = 'pi_flexform';
    ExtensionManagementUtility::addPiFlexFormValue(
        $pluginSignatureStats,
        'FILE:EXT:rescue_reports/Configuration/FlexForms/statistics.xml'
    );

    // Sidebar-Plugin
    ExtensionUtility::registerPlugin(
        'RescueReports',
        'Sidebar',
        'Rescue Reports: Sidebar',
        'rescue_reports_sidebar'
    );

    $pluginSignatureSidebar = 'rescuereports_sidebar';
    $GLOBALS['TCA']['tt_content']['types']['list']['subtypes_addlist'][$pluginSignatureSidebar] = 'pi_flexform';
    ExtensionManagementUtility::addPiFlexFormValue(
        $pluginSignatureSidebar,
        'FILE:EXT:rescue_reports/Configuration/FlexForms/sidebar.xml'
    );

    // Cards-Plugin
    ExtensionUtility::registerPlugin(
        'RescueReports',
        'Cards',
        'Rescue Reports: Cards',
        'rescue_reports_cards'
    );

    $pluginSignatureCards = 'rescuereports_cards';
    $GLOBALS['TCA']['tt_content']['types']['list']['subtypes_addlist'][$pluginSignatureCards] = 'pi_flexform';
    $GLOBALS['TCA']['tt_content']['types']['list']['subtypes_excludelist'][$pluginSignatureCards] = 'layout,select_key,pages,recursive';
    ExtensionManagementUtility::addPiFlexFormValue(
        $pluginSignatureCards,
        'FILE:EXT:rescue_reports/Configuration/FlexForms/cards.xml'
    );

    // RSS-Feed-Plugin
    ExtensionUtility::registerPlugin(
        'RescueReports',
        'Rss',
        'Rescue Reports: RSS-Feed',
        'rescue_reports_rss'
    );

    $pluginSignatureRss = 'rescuereports_rss';
    $GLOBALS['TCA']['tt_content']['types']['list']['subtypes_addlist'][$pluginSignatureRss] = 'pi_flexform';
    ExtensionManagementUtility::addPiFlexFormValue(
        $pluginSignatureRss,
        'FILE:EXT:rescue_reports/Configuration/FlexForms/rss.xml'
    );

    ExtensionManagementUtility::addStaticFile(
        'rescue_reports',
        'Configuration/TypoScript',
        'Rescue Reports Setup'
    );

    ExtensionManagementUtility::addLLrefForTCAdescr(
        'tx_rescuereports_domain_model_event',
        'EXT:rescue_reports/Resources/Private/Language/locallang_csh_tx_rescuereports_domain_model_event.xlf'
    );

    $versionInformation = new Typo3Version();
    if ($versionInformation->getMajorVersion() < 12) {
        ExtensionManagementUtility::allowTableOnStandardPages(
            'tx_rescuereports_domain_model_event'
        );
    }
})();