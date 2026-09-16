<?php

use Crossmedia\Fourallportal\Mapping\FalMapping;
use Crossmedia\Fourallportal\Mapping\MappingRegister;

return [
  'ctrl' => [
    'title' => 'LLL:EXT:fourallportal/Resources/Private/Language/locallang_db.xlf:tx_fourallportal_domain_model_module',
    'label' => 'connector_name',
    'tstamp' => 'tstamp',
    'crdate' => 'crdate',
    'rootLevel' => 1,
    'enablecolumns' => [],
    'sortby' => 'sorting',
    'default_sortby' => 'sorting ASC',
    'searchFields' => 'connector_name,mapping_class,config_hash,last_event_id,shell_path,storage_pid,server',
    'iconfile' => 'EXT:fourallportal/Resources/Public/Icons/tx_fourallportal_domain_model_module.gif',
    'security' => [
      'ignorePageTypeRestriction' => true,
    ],
  ],
  'types' => [
    '1' => ['showitem' => 'connector_name, module_name, mapping_class, enable_dynamic_model, contains_dimensions, config_hash, last_event_id, last_received_event_id, shell_path, storage_pid, fal_storage, usage_flag, test_object_uuid, server'],
  ],
  'columns' => [
    'connector_name' => [
      'exclude' => true,
      'label' => 'LLL:EXT:fourallportal/Resources/Private/Language/locallang_db.xlf:tx_fourallportal_domain_model_module.connector_name',
      'config' => [
        'type' => 'input',
        'size' => 30,
        'eval' => 'trim'
      ],
    ],
    'module_name' => [
      'exclude' => true,
      'label' => 'LLL:EXT:fourallportal/Resources/Private/Language/locallang_db.xlf:tx_fourallportal_domain_model_module.module_name',
      'config' => [
        'type' => 'input',
        'size' => 30,
        'eval' => 'trim'
      ],
    ],
    'mapping_class' => [
      'exclude' => true,
      'label' => 'LLL:EXT:fourallportal/Resources/Private/Language/locallang_db.xlf:tx_fourallportal_domain_model_module.mapping_class',
      'onChange' => 'reload',
      'config' => [
        'type' => 'select',
        'renderType' => 'selectSingle',
        'itemsProcFunc' => MappingRegister::class . '->getTcaSelectItems',
        'items' => [
          [
            'label' => '',
            'value' => '',
          ]
        ],
      ],
    ],
    'config_hash' => [
      'exclude' => true,
      'label' => 'LLL:EXT:fourallportal/Resources/Private/Language/locallang_db.xlf:tx_fourallportal_domain_model_module.config_hash',
      'config' => [
        'type' => 'passthrough',
      ],
    ],
    'enable_dynamic_model' => [
      'exclude' => true,
      'label' => 'LLL:EXT:fourallportal/Resources/Private/Language/locallang_db.xlf:tx_fourallportal_domain_model_module.enable_dynamic_model',
      'config' => [
        'type' => 'check',
        'default' => 1
      ],
    ],
    'contains_dimensions' => [
      'exclude' => true,
      'label' => 'LLL:EXT:fourallportal/Resources/Private/Language/locallang_db.xlf:tx_fourallportal_domain_model_module.contains_dimensions',
      'config' => [
        'type' => 'check',
        'default' => 1
      ],
    ],
    'last_event_id' => [
      'exclude' => true,
      'label' => 'LLL:EXT:fourallportal/Resources/Private/Language/locallang_db.xlf:tx_fourallportal_domain_model_module.last_event_id',
      'config' => [
        'type' => 'passthrough',
      ]
    ],
    'last_received_event_id' => [
      'exclude' => true,
      'label' => 'LLL:EXT:fourallportal/Resources/Private/Language/locallang_db.xlf:tx_fourallportal_domain_model_module.last_received_event_id',
      'config' => [
        'type' => 'passthrough',
      ]
    ],
    'shell_path' => [
      'exclude' => true,
      'label' => 'LLL:EXT:fourallportal/Resources/Private/Language/locallang_db.xlf:tx_fourallportal_domain_model_module.shell_path',
      //'displayCond' => 'FIELD:mapping_class:=:' . \Crossmedia\Fourallportal\Mapping\FalMapping::class,
      'config' => [
        'type' => 'input',
        'size' => 30,
        'eval' => 'trim'
      ],
    ],
    'test_object_uuid' => [
      'exclude' => true,
      'label' => 'LLL:EXT:fourallportal/Resources/Private/Language/locallang_db.xlf:tx_fourallportal_domain_model_module.test_object_uuid',
      'config' => [
        'type' => 'input',
        'size' => 30,
        'eval' => 'trim'
      ],
    ],
    'storage_pid' => [
      'exclude' => true,
      'label' => 'LLL:EXT:fourallportal/Resources/Private/Language/locallang_db.xlf:tx_fourallportal_domain_model_module.storage_pid',
      'displayCond' => 'FIELD:mapping_class:!=:' . FalMapping::class,
      'config' => [
        'type' => 'group',
        'allowed' => 'pages',
        'size' => 1,
        'hideSuggest' => 1
      ]
    ],
    'fal_storage' => [
      'exclude' => true,
      'displayCond' => 'FIELD:mapping_class:=:' . FalMapping::class,
      'label' => 'LLL:EXT:fourallportal/Resources/Private/Language/locallang_db.xlf:tx_fourallportal_domain_model_module.storage_pid',
      'config' => [
        'type' => 'select',
        'renderType' => 'selectSingle',
        'foreign_table' => 'sys_file_storage',
        'minitems' => 1,
        'maxitems' => 1,
      ],
    ],
    'usage_flag' => [
      'exclude' => true,
      'displayCond' => 'FIELD:mapping_class:=:' . FalMapping::class,
      'label' => 'LLL:EXT:fourallportal/Resources/Private/Language/locallang_db.xlf:tx_fourallportal_domain_model_module.usage_flag',
      'config' => [
        'type' => 'select',
        'renderType' => 'selectSingle',
        'items' => [
          [
            'label' => 'Original',
            'value' => 'Original',
          ],
          [
            'label' => 'WebAll',
            'value' => 'WebAll',
          ]
        ],
        'minitems' => 1,
        'maxitems' => 1,
      ],
    ],
    'server' => [
      'exclude' => true,
      'label' => 'LLL:EXT:fourallportal/Resources/Private/Language/locallang_db.xlf:tx_fourallportal_domain_model_module.server',
      'config' => [
        'type' => 'select',
        'renderType' => 'selectSingle',
        'foreign_table' => 'tx_fourallportal_domain_model_server',
      ],
    ],
  ],
];
