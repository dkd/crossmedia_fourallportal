<?php

declare(strict_types=1);

$conf = $_EXTCONF ?? null;

defined('TYPO3') or die('Access denied.');

use Crossmedia\Fourallportal\Controller\EventController;
use Crossmedia\Fourallportal\Controller\ServerController;
use Crossmedia\Fourallportal\Log\SystemLogDatabaseWriter;
use Crossmedia\Fourallportal\Mapping\FalMapping;
use Crossmedia\Fourallportal\Mapping\MappingRegister;
use Psr\Log\LogLevel as LogLevelAlias;
use TYPO3\CMS\Extbase\Utility\ExtensionUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Core\Environment;

//$backendConfiguration = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(
//  \TYPO3\CMS\Core\Configuration\ExtensionConfiguration::class
//)->get('fourallportal');

if (!isset($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['fourallportal'])) {
  $GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['fourallportal'] = [
    'clientConnectTimeout' => 20,
    'clientTransferTimeout' => 60,
    'eventDeferralTTL' => 86400,
  ];
}

// register database logs writer
$GLOBALS['TYPO3_CONF_VARS']['LOG']['Crossmedia']['Fourallportal']['writerConfiguration'] = [
  LogLevelAlias::WARNING => [
    SystemLogDatabaseWriter::class => [
      'table' => 'sys_log'
    ],
  ],
  LogLevelAlias::ERROR => [
    SystemLogDatabaseWriter::class => [
      'table' => 'sys_log'
    ],
  ],
];

// TODO: should use Symfony
//$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['extbase']['commandControllers'][$_EXTKEY] = \Crossmedia\Fourallportal\Command\FourallportalCommandController::class;

MappingRegister::registerMapping(
  FalMapping::class,
  [
    'xmp.cm_image.orientation' => false, // ignored for now
    'xmp.cm_image.width_mm' => false, // ignored for now
    'xmp.cm_image.height_mm' => false, // ignored for now
    'xmp.cm_image.has_clippingpath' => false, // ignored for now
    'xmp.cm_image.colorprofile' => false, // ignored for now
    'xmp.cm_image.colordepth' => false, // ignored for now
    'xmp.cm_image.colorspace' => false, // ignored for now
    'xmp.cm_image.resolution_dpi' => false, // ignored for now
    'xmp.cm_image.has_alpha' => false, // ignored for now,
    'standardtest_image_ceexternalid' => false, // Ignored; no connector exists for "standardtest" which is required by this relation
    'idnt_mkp_txt_file_id' => false, // Ignored; no connector exists for "idnt_mkp_txt" which is required by this relation
    'succ_stories_main_image' => false, // Ignored; no connector exists for "succ_stories" which is required by this relation
    'kits_option_option_kit_downloads' => false, // Ignored; no connector exists for "kits_option" which is required by this relation
    'data_owner_user' => false, // Ignored; no connector exists for "contact" which is required by this relation
    'usa_his_ass_data_id' => false, // Ignored; no connector exists for "contact" which is required by this relation
    'product_tech_general_image' => false, // Ignored; no connector exists for "product_tech" which is required by this relation
    'kits_option_main_image' => false, // Ignored; no connector exists for "kits_option" which is required by this relation
    'data_created_by' => false, // Ignored; no connector exists for "contact" which is required by this relation
    'product_tech_main_image' => false, // Ignored; no connector exists for "product_tech" which is required by this relation
    'product_tech_learn_more_image' => false, // Ignored; no connector exists for "product_tech" which is required by this relation
    'standardtest_text_ceexternalid' => false, // Ignored; no connector exists for "standardtest" which is required by this relation
    'hedgehog_main_image' => false, // Ignored; no connector exists for "hedgehog" which is required by this relation
    'data_mod_by' => false, // Ignored; no connector exists for "contact" which is required by this relation
    'kits_option_option_kit_main_videos' => false, // Ignored; no connector exists for "kits_option" which is required by this relation
    'data_owner_role' => false, // Ignored; no connector exists for "ce_role" which is required by this relation
    'standardtest_reference_ceexternalid' => false, // Ignored; no connector exists for "standardtest" which is required by this relation
    'product_tech_assistance_image' => false, // Ignored; no connector exists for "product_tech" which is required by this relation
    'product_tech_availability_image' => false, // Ignored; no connector exists for "product_tech" which is required by this relation
    'product_main_image' => false, // Ignored; requires custom property (CEExternalId w/o relatedModule)
    'product_background_image' => false, // Ignored; requires custom property (CEExternalId w/o relatedModule)
    'product_cat_product_cat_media' => false, // Ignored; requires custom property (CEExternalId w/o relatedModule)
    'product_cat_main_image' => false, // Ignored; requires custom property (CEExternalId w/o relatedModule)
    'product_main_video' => false, // Ignored; requires custom property (CEExternalId w/o relatedModule)
  ]
);

//if (!is_array($GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['fourallportal_classes'])) {
//  $GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['fourallportal_classes'] = array(
//    'frontend' => PhpFrontend::class,
//    'backend' => SimpleFileBackend::class,
//    'options' => [
//      'defaultLifetime' => 9999999999, // Entries in this cache have extremely long life and won't auto-expire
//    ],
//  );
//}

$directories = [
  'typo3temp/var/logs/fourallportal',
];

foreach ($directories as $directory) {
  $fullPath = Environment::getProjectPath() . '/' . $directory;
  if (!is_dir($fullPath)) {
    GeneralUtility::mkdir_deep($fullPath);
  }
}

ExtensionUtility::configurePlugin('Crossmedia.Fourallportal', 'module', [
  EventController::class => ['index', 'check', 'reset', 'execute', 'sync'],
  ServerController::class => ['index', 'check', 'disable', 'enable', 'delete', 'restartSynchronisation', 'module']
]);
