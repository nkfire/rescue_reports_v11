<?php
return [
    'ctrl' => [
        'title' => 'Organisation',
        'label' => 'name',
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'cruser_id' => 'cruser_id',
        'languageField' => 'sys_language_uid',
        'transOrigPointerField' => 'l10n_parent',
        'transOrigDiffSourceField' => 'l10n_diffsource',
        'delete' => 'deleted',
        'enablecolumns' => ['disabled' => 'hidden'],
        'iconfile' => 'EXT:rescue_reports/Resources/Public/Icons/tx_rescuereports_domain_model_organisation.svg',
    ],
    'columns' => [
        'name' => [
            'label' => 'Name',
            'config' => [
                'type' => 'input',
                'size' => 30,
                'eval' => 'trim,required',
            ],
        ],
        'abbreviation' => [
            'label' => 'Abkürzung',
            'config' => [
                'type' => 'input',
                'size' => 10,
                'eval' => 'trim',
            ],
        ],
        'icon' => [
            'exclude' => true,
            'label' => 'Symbol (Emoji)',
            'config' => [
                'type' => 'input',
                'eval' => 'trim',
                'size' => 5
            ],
        ],
    ],
    'types' => [
        '0' => ['showitem' => 'name, abbreviation, icon'],
    ],
];
