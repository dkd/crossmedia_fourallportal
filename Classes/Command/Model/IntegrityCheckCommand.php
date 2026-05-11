<?php

namespace Crossmedia\Fourallportal\Command\Model;

use Crossmedia\Fourallportal\DynamicModel\DynamicModelRegister;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Database\Query\Restriction\EndTimeRestriction;
use TYPO3\CMS\Core\Database\Query\Restriction\FrontendGroupRestriction;
use TYPO3\CMS\Core\Database\Query\Restriction\FrontendRestrictionContainer;
use TYPO3\CMS\Core\Database\Query\Restriction\HiddenRestriction;
use TYPO3\CMS\Core\Database\Query\Restriction\StartTimeRestriction;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Persistence\Generic\Mapper\DataMapper;

#[AsCommand(
    name: 'fourallportal:model:integrity-check',
    description: 'Checks the integrity of relations between dynamic models'
)]
class IntegrityCheckCommand extends Command
{
    /**
     * {@inheritDoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);
        $io->title($this->getDescription());
        $models = DynamicModelRegister::getModelClassNamesRegisteredForAutomaticHandling();
        $dataMapper = GeneralUtility::makeInstance(DataMapper::class);
        $tableNames = [];
        foreach ($models as $model) {
            try {
                $tableNames[] = $dataMapper->getDataMap($model)->getTableName();
            } catch (\Throwable $throwable) {
            }
        }
        foreach ($tableNames as $tableName) {
            try {
                $columns = $GLOBALS['TCA'][$tableName]['columns'] ?? [];
                $columns = array_filter(
                    $columns,
                    fn ($config) => !empty($config['config']['MM'] ?? null)
                        && !empty($config['config']['foreign_table'] ?? null)
                        && in_array($config['config']['foreign_table'] ?? null, $tableNames),
                );

                if (empty($columns)) {
                    $output->writeln(' Nothing to check. Skip');
                    continue;
                }

                foreach ($columns as $columnName => $configuration) {
                    $this->testIntegrityForTable(
                        $io,
                        $tableName,
                        $columnName,
                        $configuration
                    );
                }
            } catch (\Throwable $throwable) {
            }
        }

        return Command::SUCCESS;
    }

    private function testIntegrityForTable(
        SymfonyStyle $style,
        string $tableName,
        string $columnName,
        array $configuration
    ): void {
        $localMap = $this->buildLanguageMapForTable($tableName);
        $foreignMap = $this->buildLanguageMapForTable($configuration['config']['foreign_table']);
        $relationTableName = $configuration['config']['MM'];
        $relationTableColumn = empty($configuration['config']['MM_opposite_field'] ?? null) ? 'uid_local' : 'uid_foreign';
        $relationOppositColumn = $relationTableColumn === 'uid_local' ? 'uid_foreign' : 'uid_local';
        /*
         * Contains the result of the check
         * Key is the uid of the base data set of language 0
         * It contains
         *   - the list of related data uids to the foreign table for language 0
         *   - the list of translations, where the related data does not match in amount or uids
         */
        $integrityResult = [];
        $errors = false;
        // Process the language 0 first to build the base structure
        foreach ($localMap as $itemUid => $itemConfig) {
            if ($itemConfig['language'] > 0) {
                continue;
            }
            $integrityResult[$itemUid] = [
                'relations' => [],
                'translations' => [],
                'object_id' => $itemConfig['object_id'],
                'invalid' => $itemConfig['invalid']
            ];
            if ($itemConfig['invalid']) {
                $errors = true;
            }
            $queryBuilder = $this->getQueryBuilder($relationTableName);
            $rows = $queryBuilder
                ->select($relationOppositColumn)
                ->from($relationTableName)
                ->where($queryBuilder->expr()->eq($relationTableColumn, (int)$itemUid))
                ->executeQuery()
                ->fetchAllAssociative()
            ;

            foreach ($rows as $row) {
                $status = (int)($foreignMap[$row[$relationOppositColumn]] ?? 0) > 0 ? 'OK' : 'INVALD';
                $integrityResult[$itemUid]['relations'][$row[$relationOppositColumn]] = $status;
                if ($status === 'INVALID') {
                    $errors = true;
                }
            }
        }

        foreach ($localMap as $itemUid => $itemConfig) {
            if ($itemConfig['language'] == 0) {
                continue;
            }
            $queryBuilder = $this->getQueryBuilder($relationTableName);
            $rows = $queryBuilder
                ->select($relationOppositColumn)
                ->from($relationTableName)
                ->where($queryBuilder->expr()->eq($relationTableColumn, (int)$itemUid))
                ->executeQuery()
                ->fetchAllAssociative()
            ;

            $parentUid = $itemConfig['parent'];
            $relations = [];
            foreach ($rows as $row) {
                $foreignUid = $row[$relationOppositColumn];
                $foreignUidResolved = null;
                if (array_key_exists($row[$relationOppositColumn], $foreignMap) && $foreignMap[$row[$relationOppositColumn]]['parent'] > 0) {
                    $foreignUidResolved = $foreignMap[$row[$relationOppositColumn]]['parent'];
                } else {
                    $foreignUid = 'N/A';
                    $foreignUidResolved = $row[$relationOppositColumn];
                }
                $relations[$foreignUidResolved] = $foreignUid;
            }

            // Difference in relation count
            if (count($integrityResult[$parentUid]['relations'] ?? []) !== count($relations)) {
                $integrityResult[$parentUid]['translations'][$itemConfig['language']] = $relations;
                $errors = true;
            } else {
                $missmatch = array_filter(
                    $relations,
                    fn ($originalKey) => !in_array($originalKey, $integrityResult[$parentUid]['relations'])
                );
                if (count($missmatch) > 0) {
                    $integrityResult[$parentUid]['translations'][$itemConfig['language']] = $relations;
                    $errors = true;
                }
            }
        }

        if ($errors === false) {
            $style->info('Integrity result for table ' . $tableName . ' on column ' . $columnName . ': OK');
        } else {
            $style->warning('Integrity result for table ' . $tableName . ' on column ' . $columnName . ': Found issues');
            foreach ($integrityResult as $rootUid => $item) {
                if (($item['invalid'] ?? false) === true) {
                    if (count($item['translations']) > 0) {
                        $tableRows = [];
                        foreach ($item['translations'] as $languageId => $translation) {
                            $tableRows[] = [
                                $tableName,
                                $columnName,
                                $item['object_id'],
                                $languageId,
                                'Possible orphan record. Points to non existing l10n_parent ' . $rootUid
                            ];
                        }
                        $style->table(
                            ['Table', 'Field', 'Object ID', 'Language', 'Issue'],
                            $tableRows
                        );
                    } else {
                        $style->warning('Possible orphan relation for uid local ' . $item['object_id']);
                    }
                } else {
                    if (!empty($item['translations'])) {
                        $tableRows = [];
                        foreach ($item['translations'] as $languageId => $translation) {
                            $reason = 'Amount of translation does not match.';
                            if (count($item['relations'] ?? []) === count($translation)) {
                                $reason = 'Relation point to a wrong or missing parent';
                            }

                            $tableRows[] = [
                                $tableName,
                                $columnName,
                                $item['object_id'] ?? 'N/A',
                                $rootUid,
                                $languageId,
                                count($item['relations'] ?? []),
                                count($translation),
                                $reason
                            ];
                        }
                        $style->table(
                            ['Table', 'Field', 'Object ID', 'Root uid', 'Language', 'Root relations', 'Localized translations', 'Reason'],
                            $tableRows
                        );
                    } else {
                        $style->warning('Missing localizations for l10n_parent ' . $item['object_id']);
                    }
                }
            }
        }
    }

    /**
     * Creates a basic language mapping for a given table
     *
     * [
     *      'data uid' => [
     *          'translations' => [],
     *          'language' => <language uid>,
     *          'parent' => <l10n parent data uid>,
     *          'invalid' => 'true in case the l10n parent is missing'
     *      ]
     * ]
     *
     * @param string $tableName
     * @return array
     */
    private function buildLanguageMapForTable(string $tableName): array
    {
        $queryBuilder = $this->getQueryBuilder($tableName);
        $query = $queryBuilder
            ->select(
                'uid',
                'remote_id',
                $GLOBALS['TCA'][$tableName]['ctrl']['languageField'],
                $GLOBALS['TCA'][$tableName]['ctrl']['transOrigPointerField']
            )
            ->from($tableName)
            ->orderBy($GLOBALS['TCA'][$tableName]['ctrl']['transOrigPointerField'], 'asc')
            ->addOrderBy($GLOBALS['TCA'][$tableName]['ctrl']['languageField'], 'asc')
            ->executeQuery()
        ;

        $languageMap = [];
        while ($row = $query->fetchAssociative()) {
            $l10nParent = (int)($row[$GLOBALS['TCA'][$tableName]['ctrl']['transOrigPointerField']]);
            $languageMap[$row['uid']] = [
                'translations' => [],
                'language' => $row[$GLOBALS['TCA'][$tableName]['ctrl']['languageField']],
                'parent' => $l10nParent,
                'object_id' => $row['remote_id'],
                'invalid' => false
            ];

            if ($l10nParent > 0) {
                if (!array_key_exists($l10nParent, $languageMap)) {
                    $languageMap[$l10nParent] = [
                        'translations' => [],
                        'language' => 0,
                        'parent' => 0,
                        'object_id' => $row['remote_id'],
                        'invalid' => true
                    ];
                }
                $languageMap[$l10nParent]['translations'][] = $row['uid'];
            }
        }

        return $languageMap;
    }

    private function getQueryBuilder(string $tableName): QueryBuilder
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable($tableName);
        $queryBuilder
            ->getRestrictions()
            ->removeByType(FrontendRestrictionContainer::class)
            ->add(new DeletedRestriction())
        ;

        return $queryBuilder;
    }
}
