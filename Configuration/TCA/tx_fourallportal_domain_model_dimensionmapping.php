<?php
return [
  'ctrl' => [
    'title' => 'LLL:EXT:fourallportal/Resources/Private/Language/locallang_db.xlf:tx_fourallportal_domain_model_dimensionmapping',
    'label' => 'language',
    'tstamp' => 'tstamp',
    'crdate' => 'crdate',
    'rootLevel' => 1,
    'enablecolumns' => [ ],
    'iconfile' => 'EXT:fourallportal/Resources/Public/Icons/tx_fourallportal_domain_model_dimensionmapping.gif',
    'enablecolumns' => ['disabled' => 'active'],
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
        'renderType' => 'selectSingle',
        'special' => 'languages',
        'default' => 0,
      ]
    ],
    'metric_or_imperial' => [
      'label' => 'Imperial/Metric unit',
      'config' => [
        'type' => 'select',
        'renderType' => 'selectSingle',
        'default' => 'Metric',
        'items' => [
          ['label' => 'Metric', 'value' => 'Metric'],
          ['label' => 'Imperial', 'value' => 'Imperial'],
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
        'default' => 0,
        'items' => [
          ['label' => 'LLL:EXT:lang/locallang_core.xlf:labels.enabled']
        ],
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
