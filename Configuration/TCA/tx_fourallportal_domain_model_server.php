<?php
return [
  'ctrl' => [
    'title' => 'LLL:EXT:fourallportal/Resources/Private/Language/locallang_db.xlf:tx_fourallportal_domain_model_server',
    'label' => 'domain',
    'tstamp' => 'tstamp',
    'crdate' => 'crdate',
    'delete' => 'deleted',
    'rootLevel' => 1,
    'enablecolumns' => [
      'disabled' => 'active',
    ],
    'searchFields' => 'domain,customer_name,username,password,active,modules',
    'iconfile' => 'EXT:fourallportal/Resources/Public/Icons/tx_fourallportal_domain_model_server.gif',
    'security' => [
      'ignorePageTypeRestriction' => true,
    ],
  ],
  'types' => [
    '1' => ['showitem' => 'active, domain, customer_name, username, password, modules, dimension_mappings'],
  ],
  'columns' => [
    'domain' => [
      'exclude' => true,
      'label' => 'LLL:EXT:fourallportal/Resources/Private/Language/locallang_db.xlf:tx_fourallportal_domain_model_server.domain',
      'config' => [
        'type' => 'input',
        'size' => 30,
        'eval' => 'trim'
      ],
    ],
    'customer_name' => [
      'exclude' => true,
      'label' => 'LLL:EXT:fourallportal/Resources/Private/Language/locallang_db.xlf:tx_fourallportal_domain_model_server.customer_name',
      'config' => [
        'type' => 'input',
        'size' => 30,
        'eval' => 'trim'
      ],
    ],
    'username' => [
      'exclude' => true,
      'label' => 'LLL:EXT:fourallportal/Resources/Private/Language/locallang_db.xlf:tx_fourallportal_domain_model_server.username',
      'config' => [
        'type' => 'input',
        'size' => 30,
        'eval' => 'trim'
      ],
    ],
    'password' => [
      'exclude' => true,
      'label' => 'LLL:EXT:fourallportal/Resources/Private/Language/locallang_db.xlf:tx_fourallportal_domain_model_server.password',
      'config' => [
        'type' => 'input',
        'size' => 30,
        'eval' => 'trim'
      ],
    ],
    'active' => [
      'exclude' => true,
      'label' => 'LLL:EXT:fourallportal/Resources/Private/Language/locallang_db.xlf:tx_fourallportal_domain_model_server.active',
      'config' => [
        'type' => 'check',
        'default' => 0,
      ]
    ],
    'modules' => [
      'exclude' => true,
      'label' => 'LLL:EXT:fourallportal/Resources/Private/Language/locallang_db.xlf:tx_fourallportal_domain_model_server.modules',
      'config' => [
        'type' => 'inline',
        'foreign_table' => 'tx_fourallportal_domain_model_module',
        'foreign_field' => 'server',
        'foreign_sortby' => 'sorting',
        'foreign_default_sortby' => 'sorting ASC',
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
    'dimension_mappings' => [
      'exclude' => true,
      'label' => 'LLL:EXT:fourallportal/Resources/Private/Language/locallang_db.xlf:tx_fourallportal_domain_model_server.dimensionmappings',
      'config' => [
        'type' => 'inline',
        'foreign_table' => 'tx_fourallportal_domain_model_dimensionmapping',
        'foreign_field' => 'server',
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
