<?php
return [
    'ctrl' => [
        'label_alt' => 'remote_id',
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'versioningWS' => false,
        'languageField' => 'sys_language_uid',
        'transOrigPointerField' => 'l10n_parent',
        'transOrigDiffSourceField' => 'l10n_diffsource',
        'delete' => 'deleted',
        'searchFields' => 'remote_id',
    ],
    'types' => [
        '1' => [
            'showitem' => 'remote_id',
        ],
    ],
    'columns' => [
        'sys_language_uid' => [
            'exclude' => true,
            'label' => 'LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.language',
            'config' => ['type' => 'language'],
        ],
        'l10n_parent' => [
            'displayCond' => 'FIELD:sys_language_uid:>:0',
            'exclude' => 1,
            'label' => 'LLL:EXT:lang/locallang_general.xlf:LGL.l18n_parent',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'items' => [
                    ['', 0],
                ],
                'foreign_table_where' => 'AND ###TABLE###.pid=###CURRENT_PID### AND ###TABLE###.sys_language_uid IN (-1,0)',
            ],
        ],
        'l10n_diffsource' => [
            'config' => [
                'type' => 'passthrough',
            ],
        ],
        't3ver_label' => [
            'label' => 'LLL:EXT:lang/locallang_general.xlf:LGL.versionLabel',
            'config' => [
                'type' => 'input',
                'size' => 30,
                'max' => 255,
            ],
        ],
        'remote_id' => [
            'exclude' => true,
            'label' => 'LLL:EXT:fourallportal/Resources/Private/Language/locallang.xlf:universal.remote_id',
            'config' => [
                'type' => 'input',
                'size' => 64,
                'eval' => 'trim',
            ],
        ],
    ],
];
