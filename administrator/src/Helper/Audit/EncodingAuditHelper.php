<?php
/**
 * @package     ContentBuilder NG
 * @author      XDA+GIL
 * @link        https://breezingforms-ng.vcmb.fr
 * @copyright   Copyright © 2026 by XDA+GIL
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace CB\Component\Contentbuilderng\Administrator\Helper\Audit;

\defined('_JEXEC') or die('Restricted access');

use Joomla\Database\DatabaseInterface;

final class EncodingAuditHelper
{
    private const TARGET_CHARSET = 'utf8mb4';
    private const TARGET_COLLATION = 'utf8mb4_0900_ai_ci';

    /**
     * @param array<int,string> $tables
     * @return array{
     *   0:array<int,array{table:string,collation:string,expected:string}>,
     *   1:array<int,array{table:string,column:string,charset:string,collation:string}>,
     *   2:array<int,array{collation:string,count:int,tables:array<int,string>}>,
     *   3:array<int,string>
     * }
     */
    public static function inspect(DatabaseInterface $db, array $tables, string $prefix, callable $toAlias): array
    {
        $tableIssues = [];
        $columnIssues = [];
        $mixedCollations = [];
        $errors = [];
        $tablesByCollation = [];

        if ($tables === []) {
            return [$tableIssues, $columnIssues, $mixedCollations, $errors];
        }

        $quotedTables = array_map(
            static fn(string $tableName): string => $db->quote($tableName),
            $tables
        );
        $inClause = implode(', ', $quotedTables);

        try {
            $db->setQuery(
                'SELECT TABLE_NAME, TABLE_COLLATION'
                . ' FROM information_schema.TABLES'
                . ' WHERE TABLE_SCHEMA = DATABASE()'
                . ' AND TABLE_NAME IN (' . $inClause . ')'
            );
            $tableRows = $db->loadAssocList() ?: [];
        } catch (\Throwable $e) {
            $errors[] = 'Could not inspect table collations: ' . $e->getMessage();
            $tableRows = [];
        }

        $collationStats = [];

        foreach ($tableRows as $row) {
            $tableName = (string) ($row['TABLE_NAME'] ?? $row['table_name'] ?? '');
            $collation = (string) ($row['TABLE_COLLATION'] ?? $row['table_collation'] ?? '');

            if ($tableName === '') {
                continue;
            }

            if ($collation !== '') {
                $collationStats[$collation] = ($collationStats[$collation] ?? 0) + 1;
                $tablesByCollation[$collation][] = $toAlias($tableName, $prefix);
            }

            if ($collation === '' || strcasecmp($collation, self::TARGET_COLLATION) !== 0) {
                $tableIssues[] = [
                    'table' => $toAlias($tableName, $prefix),
                    'collation' => $collation,
                    'expected' => self::TARGET_COLLATION,
                ];
            }
        }

        arsort($collationStats);
        foreach ($collationStats as $collation => $count) {
            $collationTables = array_values(array_unique((array) ($tablesByCollation[$collation] ?? [])));
            sort($collationTables, SORT_NATURAL | SORT_FLAG_CASE);
            $mixedCollations[] = [
                'collation' => (string) $collation,
                'count' => (int) $count,
                'tables' => $collationTables,
            ];
        }

        try {
            $db->setQuery(
                'SELECT TABLE_NAME, COLUMN_NAME, CHARACTER_SET_NAME, COLLATION_NAME'
                . ' FROM information_schema.COLUMNS'
                . ' WHERE TABLE_SCHEMA = DATABASE()'
                . ' AND TABLE_NAME IN (' . $inClause . ')'
                . ' AND COLLATION_NAME IS NOT NULL'
            );
            $columnRows = $db->loadAssocList() ?: [];
        } catch (\Throwable $e) {
            $errors[] = 'Could not inspect column collations: ' . $e->getMessage();
            $columnRows = [];
        }

        foreach ($columnRows as $row) {
            $tableName = (string) ($row['TABLE_NAME'] ?? $row['table_name'] ?? '');
            $columnName = (string) ($row['COLUMN_NAME'] ?? $row['column_name'] ?? '');
            $charset = strtolower((string) ($row['CHARACTER_SET_NAME'] ?? $row['character_set_name'] ?? ''));
            $collation = (string) ($row['COLLATION_NAME'] ?? $row['collation_name'] ?? '');

            if ($tableName === '' || $columnName === '') {
                continue;
            }

            if ($charset !== self::TARGET_CHARSET || strcasecmp($collation, self::TARGET_COLLATION) !== 0) {
                $columnIssues[] = [
                    'table' => $toAlias($tableName, $prefix),
                    'column' => $columnName,
                    'charset' => $charset,
                    'collation' => $collation,
                ];
            }
        }

        usort(
            $tableIssues,
            static fn(array $a, array $b): int => strcmp((string) ($a['table'] ?? ''), (string) ($b['table'] ?? ''))
        );
        usort(
            $columnIssues,
            static fn(array $a, array $b): int => strcmp(
                ((string) ($a['table'] ?? '')) . ':' . ((string) ($a['column'] ?? '')),
                ((string) ($b['table'] ?? '')) . ':' . ((string) ($b['column'] ?? ''))
            )
        );

        return [$tableIssues, $columnIssues, $mixedCollations, $errors];
    }
}
