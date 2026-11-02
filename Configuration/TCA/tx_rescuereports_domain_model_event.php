<?php

return [
    'ctrl' => [
        'title' => 'LLL:EXT:rescue_reports/Resources/Private/Language/locallang_db.xlf:tx_rescuereports_domain_model_event',
        'label' => 'title',
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'cruser_id' => 'cruser_id',
        'languageField' => 'sys_language_uid',
        'transOrigPointerField' => 'l10n_parent',
        'transOrigDiffSourceField' => 'l10n_diffsource',
        'delete' => 'deleted',
        'enablecolumns' => [
            'disabled' => 'hidden',
        ],
        'security' => [
            'ignorePageTypeRestriction' => true,
        ],
        'searchFields' => 'title,description',
        'iconfile' => 'EXT:rescue_reports/Resources/Public/Icons/tx_rescuereports_domain_model_event.png',
        'default_sortby' => 'ORDER BY start DESC',
    ],
    'types' => [
        '1' => [
            'showitem' => 'hidden, title, --palette--;;times, number, types, --palette--;;keywordEscalation, location, --palette--;;coordinates, disable_detail, description, slug, --div--;Eingesetzte Einheiten, stations, --div--;Fahrzeuge, vehicles, --div--;Bilder, images, --div--;Intern, internal_notes'
        ],
    ],

    'palettes' => [
        'times' => [
            'showitem' => 'start, end',
            'label' => 'Einsatzzeit',
        ],
        'coordinates' => [
            'showitem' => 'latitude, longitude',
            'label' => 'GPS-Koordinaten (optional)',
        ],
        'keywordEscalation' => [
            'showitem' => 'enable_keyword_escalation, keyword_escalation',
            'label' => 'Stichworterhöhung (optional)',
            'displayCond' => 'FIELD:enable_keyword_escalation:>:0',
        ],
    ],

    'columns' => [

        // Systemfelder
        'sys_language_uid' => [
            'exclude' => true,
            'label' => 'LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.language',
            'config' => ['type' => 'language'],
        ],
        'l18n_parent' => [
            'displayCond' => 'FIELD:sys_language_uid:>:0',
            'label' => 'LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.l18n_parent',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'items' => [['', 0]],
                'foreign_table' => 'tx_rescuereports_domain_model_event',
                'foreign_table_where' => 'AND {#tx_rescuereports_domain_model_event}.{#pid}=###CURRENT_PID###',
                'default' => 0,
            ],
        ],
        'l18n_diffsource' => ['config' => ['type' => 'passthrough']],
        'hidden' => [
            'label' => 'LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.hidden',
            'config' => ['type' => 'check', 'default' => 1],
        ],

        // Einsatzzeit
        'start' => [
            'label' => 'Einsatzbeginn',
            'config' => ['type' => 'input', 'renderType' => 'inputDateTime', 'eval' => 'datetime', 'dbType' => 'datetime', 'default' => null],
        ],
        'end' => [
            'label' => 'Einsatzende',
            'config' => ['type' => 'input', 'renderType' => 'inputDateTime', 'eval' => 'datetime', 'dbType' => 'datetime', 'default' => null],
        ],

        // Inhaltliche Felder
        'title' => [
            'label' => 'Einsatztitel',
            'config' => ['type' => 'input', 'eval' => 'trim,required'],
        ],
        'location' => [
            'label' => 'Einsatzort',
            'config' => ['type' => 'input', 'eval' => 'trim', 'default' => "Stadt, Straße // BAB 9, Richtung ...",],
        ],
        'latitude' => [
            'label' => 'Breitengrad (Latitude)',
            'config' => [
                'type' => 'input',
                'eval' => 'trim,null',
                'placeholder' => '51.12345678',
                'size' => 20,
                'default' => null,
            ],
        ],
        'longitude' => [
            'label' => 'Längengrad (Longitude)',
            'config' => [
                'type' => 'input',
                'eval' => 'trim,null',
                'placeholder' => '10.12345678',
                'size' => 20,
                'default' => null,
            ],
        ],
        'number' => [
            'label' => 'Einsatznummer',
            'config' => ['type' => 'input', 'eval' => 'trim', 'placeholder' => '26/123', 'max' => 6, 'default' => '26/'],
        ],
        'description' => [
            'label' => 'Einsatzbericht',
            'config' => ['type' => 'text', 'enableRichtext' => true, 'richtextConfiguration' => 'rescue_reports', 'rows' => 5],
        ],

        'internal_notes' => [
            'exclude' => true,
            'label'   => 'Interne Notizen',
            'config'  => [
                'type' => 'text',
                'rows' => 6,
                'cols' => 48,
            ],
        ],

        // Typen
        'types' => [
            'label' => 'Einsatzart',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'foreign_table' => 'tx_rescuereports_domain_model_type',
                'foreign_table_where' => '
                    ORDER BY tx_rescuereports_domain_model_type.title
                ',
                'itemsProcFunc' => \Nkfire\RescueReports\UserFunctions\TypeItemsProcFunc::class . '->filterDeprecatedTypes',
                'MM' => 'tx_rescuereports_event_type_mm',
                'minitems' => 0,
                'maxitems' => 1,
            ],
        ],

        // Stichworterhöhung aktivieren
        'enable_keyword_escalation' => [
            'label' => 'Stichworterhöhung verwenden',
            'onChange' => 'reload',
            'config' => [
                'type' => 'check',
                'items' => [
                    ['Stichworterhöhung als Einsatzart in Statistiken verwenden', 1],
                ],
                'default' => 0,
            ],
        ],

        // Stichworterhöhung
        'keyword_escalation' => [
            'label' => 'Stichworterhöhung auf',
            'displayCond' => 'FIELD:enable_keyword_escalation:>:0',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'foreign_table' => 'tx_rescuereports_domain_model_type',
                'foreign_table_where' => '
                    ORDER BY tx_rescuereports_domain_model_type.title
                ',
                'itemsProcFunc' => \Nkfire\RescueReports\UserFunctions\TypeItemsProcFunc::class . '->filterDeprecatedTypes',
                'MM' => 'tx_rescuereports_event_keyword_escalation_mm',
                'minitems' => 0,
                'maxitems' => 1,
            ],
        ],

        // Stationen
        'stations' => [
            'label' => 'Eingesetzte Einheiten',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectCheckBox',
                'itemsProcFunc' => 'Nkfire\\RescueReports\\Utility\\StationLabelUtility->addGroupedStations',
                'foreign_table' => 'tx_rescuereports_domain_model_station',
                'foreign_table_where' => 'AND 1=0',
                'MM' => 'tx_rescuereports_event_station_mm',
                'size' => 10,
                'maxitems' => 9999,
            ],
        ],
       'vehicles' => [
            'exclude' => true,
            'label' => 'Eingesetzte Fahrzeuge',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectMultipleSideBySide',
                //'foreign_table' => 'tx_rescuereports_domain_model_vehicle',
                'itemsProcFunc' => \Nkfire\RescueReports\Utility\EventVehicleSelectionUtility::class . '->getAvailableVehicles',
                //'foreign_table_where' => '', // ← wichtig, NICHT setzen!
                'size' => 15,
                'maxitems' => 999,
                'multiple' => true,
                'eval' => 'int',
            ],
        ],
        'slug_source' => [
            'exclude' => true,
            'label' => 'Slug Source',
            'config' => [
                'type' => 'input',
                'readOnly' => true,
            ],
        ],
        'slug' => [
            'exclude' => true,
            'label' => 'Slug',
            'config' => [
                'type' => 'slug',
                'size' => 50,
                'generatorOptions' => [
                    'fields' => ['slug_source'],
                    'fieldSeparator' => '/',
                ],
                'fallbackCharacter' => '-',
                'eval' => 'uniqueInSite',
                'default' => '',
            ],
        ],
        // Bilder
        'images' => [
            'label' => 'Bilder',
            'config' => \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::getFileFieldTCAConfig(
                'images',
                [
                    'maxitems' => 10,
                    'appearance' => [
                        'createNewRelationLinkTitle' => 'LLL:EXT:core/Resources/Private/Language/locallang_core.xlf:label.addFileReference',
                    ],
                ],
                $GLOBALS['TYPO3_CONF_VARS']['GFX']['imagefile_ext']
            ),
        ],
        'disable_detail' => [
            'label' => 'Detailansicht deaktivieren',
            'config' => [
                'type' => 'check',
                'items' => [
                    ['Keinen Link zur Detailansicht anzeigen', 1],
                ],
                'default' => 0,
            ],
        ],
    ],
];
