<?php
return [
    'ctrl' => [
        'title' => 'LLL:EXT:fourallportal/Resources/Private/Language/locallang_db.xlf:tx_fourallportal_domain_model_complextype',
        'label' => 'field_name',
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'rootLevel' => 1,
        'languageField' => 'sys_language_uid',
        'transOrigPointerField' => 'l10n_parent',
        'transOrigDiffSourceField' => 'l10n_diffsource',
        'delete' => 'deleted',
        'searchFields' => 'name,field_name',
        'iconfile' => 'EXT:fourallportal/Resources/Public/Icons/tx_fourallportal_domain_model_complextype.gif',
        'security' => [
          'ignorePageTypeRestriction' => true,
        ],
    ],
    'types' => [
        '1' => [
            'showitem' => 'name, type, label, label_max, field_name, actual_value, normalized_value, actual_value_max, normalized_value_max, cast_type, parent_uid,
                --div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:language,sys_language_uid,l10n_parent,l10n_source'
        ],
    ],
    'columns' => [
        'sys_language_uid' => [
            'exclude' => true,
            'label' => 'LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.language',
            'config' => [
                'type' => 'language',
            ],
        ],
        'l10n_parent' => [
            'displayCond' => 'FIELD:sys_language_uid:>:0',
            'label' => 'LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.l18n_parent',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'items' => [
                    0 => [
                        'label' => '',
                        'value' => 0,
                    ],
                ],
                'foreign_table_where' => 'AND tx_fourallportal_domain_model_complextype.pid=###CURRENT_PID### AND tx_fourallportal_domain_model_complextype.sys_language_uid IN (-1,0)',
                'foreign_table' => 'tx_fourallportal_domain_model_complextype',
            ],
        ],
        'l10n_diffsource' => [
            'config' => [
                'type' => 'passthrough',
            ],
        ],
        'name' => [
            'exclude' => true,
            'label' => 'LLL:EXT:fourallportal/Resources/Private/Language/locallang_db.xlf:tx_fourallportal_domain_model_complextype.name',
            'config' => [
                'type' => 'input',
                'readOnly' => true
            ]
        ],
        'type' => [
            'exclude' => true,
            'label' => 'LLL:EXT:fourallportal/Resources/Private/Language/locallang_db.xlf:tx_fourallportal_domain_model_complextype.type',
            'config' => [
                'type' => 'input',
                'readOnly' => true
            ]
        ],
        'label' => [
            'exclude' => true,
            'label' => 'LLL:EXT:fourallportal/Resources/Private/Language/locallang_db.xlf:tx_fourallportal_domain_model_complextype.label',
            'config' => [
                'type' => 'input',
                'readOnly' => true
            ]
        ],
        'label_max' => [
            'exclude' => true,
            'label' => 'LLL:EXT:fourallportal/Resources/Private/Language/locallang_db.xlf:tx_fourallportal_domain_model_complextype.label_max',
            'config' => [
                'type' => 'input',
                'readOnly' => true
            ]
        ],
        'field_name' => [
            'exclude' => true,
            'label' => 'LLL:EXT:fourallportal/Resources/Private/Language/locallang_db.xlf:tx_fourallportal_domain_model_complextype.field_name',
            'config' => [
                'type' => 'input',
                'readOnly' => true
            ]
        ],
        'actual_value' => [
            'exclude' => true,
            'label' => 'LLL:EXT:fourallportal/Resources/Private/Language/locallang_db.xlf:tx_fourallportal_domain_model_complextype.actual_value',
            'config' => [
                'type' => 'input',
                'readOnly' => true
            ]
        ],
        'normalized_value' => [
            'exclude' => true,
            'label' => 'LLL:EXT:fourallportal/Resources/Private/Language/locallang_db.xlf:tx_fourallportal_domain_model_complextype.normalized_value',
            'config' => [
                'type' => 'input',
                'readOnly' => true
            ]
        ],
        'actual_value_max' => [
            'exclude' => true,
            'label' => 'LLL:EXT:fourallportal/Resources/Private/Language/locallang_db.xlf:tx_fourallportal_domain_model_complextype.actual_value_max',
            'config' => [
                'type' => 'input',
                'readOnly' => true
            ]
        ],
        'normalized_value_max' => [
            'exclude' => true,
            'label' => 'LLL:EXT:fourallportal/Resources/Private/Language/locallang_db.xlf:tx_fourallportal_domain_model_complextype.normalized_value_max',
            'config' => [
                'type' => 'input',
                'readOnly' => true
            ]
        ],
        'cast_type' => [
            'exclude' => true,
            'label' => 'LLL:EXT:fourallportal/Resources/Private/Language/locallang_db.xlf:tx_fourallportal_domain_model_complextype.cast_type',
            'config' => [
                'type' => 'input',
                'readOnly' => true
            ]
        ],
        'parent_uid' => [
            'exclude' => true,
            'label' => 'LLL:EXT:fourallportal/Resources/Private/Language/locallang_db.xlf:tx_fourallportal_domain_model_complextype.parent_uid',
            'config' => [
                'type' => 'input',
                'readOnly' => true
            ]
        ],
    ],
];
