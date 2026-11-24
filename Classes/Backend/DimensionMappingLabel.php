<?php

declare(strict_types=1);

namespace Crossmedia\Fourallportal\Backend;

use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Record title of a dimension mapping, e.g. "locale=de_DE, channel=website (language 4, Metr)"
 */
class DimensionMappingLabel
{
    public function getTitle(array &$parameters): void
    {
        $row = $parameters['row'] ?? [];
        $language = is_array($row['language'] ?? null) ? reset($row['language']) : ($row['language'] ?? '');
        $unit = is_array($row['metric_or_imperial'] ?? null) ? reset($row['metric_or_imperial']) : ($row['metric_or_imperial'] ?? '');

        $dimensions = [];
        if (is_numeric($row['uid'] ?? null)) {
            $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
                ->getQueryBuilderForTable('tx_fourallportal_domain_model_dimension');
            $rows = $queryBuilder
                ->select('name', 'value')
                ->from('tx_fourallportal_domain_model_dimension')
                ->where($queryBuilder->expr()->eq('dimension_mapping', $queryBuilder->createNamedParameter((int)$row['uid'])))
                ->orderBy('name')
                ->executeQuery()
                ->fetchAllAssociative();
            foreach ($rows as $dimension) {
                $dimensions[] = $dimension['name'] . '=' . $dimension['value'];
            }
        }

        $details = trim('language ' . $language . ($unit !== '' ? ', ' . $unit : ''));
        $parameters['title'] = $dimensions !== []
            ? implode(', ', $dimensions) . ' (' . $details . ')'
            : $details;
    }
}
