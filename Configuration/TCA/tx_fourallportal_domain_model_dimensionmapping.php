<?php
return [
  'ctrl' => [
    'title' => 'LLL:EXT:fourallportal/Resources/Private/Language/locallang_db.xlf:tx_fourallportal_domain_model_dimensionmapping',
    'label' => 'language',
    'label_userFunc' => \Crossmedia\Fourallportal\Backend\DimensionMappingLabel::class . '->getTitle',
    'tstamp' => 'tstamp',
    'crdate' => 'crdate',
    'rootLevel' => 1,
    'enablecolumns' => [ ],
    'iconfile' => 'EXT:fourallportal/Resources/Public/Icons/tx_fourallportal_domain_model_dimensionmapping.gif',
    'security' => [
      'ignorePageTypeRestriction' => true,
    ],
  ],
  'types' => [
    '1' => ['showitem' => 'active, language, metric_or_imperial, dimensions, server'],
  ],
  'columns' => [
    'language' => [
      'exclude' => true,
      'label' => 'LLL:EXT:fourallportal/Resources/Private/Language/locallang_db.xlf:tx_fourallportal_domain_model_dimensionmapping.language',
      'config' => [
        'type' => 'language',
      ]
    ],
    'metric_or_imperial' => [
      'label' => 'LLL:EXT:fourallportal/Resources/Private/Language/locallang_db.xlf:tx_fourallportal_domain_model_dimensionmapping.metric_or_imperial',
      'config' => [
        'type' => 'select',
        'renderType' => 'selectSingle',
        'default' => 'Metr',
        'items' => [
          ['label' => 'LLL:EXT:fourallportal/Resources/Private/Language/locallang_db.xlf:tx_fourallportal_domain_model_dimensionmapping.metric_or_imperial.metric', 'value' => 'Metr'],
          ['label' => 'LLL:EXT:fourallportal/Resources/Private/Language/locallang_db.xlf:tx_fourallportal_domain_model_dimensionmapping.metric_or_imperial.imperial', 'value' => 'Imp'],
        ],
      ],
    ],
    'server' => [
      'config' => [
        'type' => 'passthrough',
      ],
    ],
    'active' => [
      'exclude' => true,
      'label' => 'LLL:EXT:fourallportal/Resources/Private/Language/locallang_db.xlf:tx_fourallportal_domain_model_dimensionmapping.active',
      'config' => [
        'type' => 'check',
        'renderType' => 'checkboxToggle',
        'default' => 1,
      ]
    ],
    'dimensions' => [
      'exclude' => true,
      'label' => 'LLL:EXT:fourallportal/Resources/Private/Language/locallang_db.xlf:tx_fourallportal_domain_model_dimensionmapping.dimensions',
      'config' => [
        'type' => 'inline',
        'foreign_table' => 'tx_fourallportal_domain_model_dimension',
        'foreign_field' => 'dimension_mapping',
        'maxitems' => 9999,
        'appearance' => [
          'collapseAll' => 1,
          'levelLinksPosition' => 'top',
          'showSynchronizationLink' => 1,
          'showPossibleLocalizationRecords' => 1,
          'showAllLocalizationLink' => 1
        ],
      ],

    ],
  ],
];
