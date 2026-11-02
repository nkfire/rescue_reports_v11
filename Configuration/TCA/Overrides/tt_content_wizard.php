<?php
declare(strict_types=1);

defined('TYPO3') or die();

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

(function (): void {
    ExtensionManagementUtility::addPageTSConfig(
        '
        mod.wizards.newContentElement.wizardItems.plugins {
            elements {
                rescuereports_eventlist {
                    iconIdentifier = content-plugin
                    title = Rescue Reports: Einsatzberichte für Feuerwehren und BOS
                    description = Zeigt eine Liste von Einsätzen an
                    tt_content_defValues {
                        CType = list
                        list_type = rescuereports_eventlist
                    }
                }
            }
            show := addToList(rescuereports_eventlist)
        }
        '
    );
})();