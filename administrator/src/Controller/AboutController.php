<?php
/**
 * @package     ContentBuilder NG
 * @author      XDA+GIL
 * @link        https://breezingforms.vcmb.fr
 * @copyright   Copyright (C) 2026 by XDA+GIL
 * @license     GNU/GPL
 */

namespace CB\Component\Contentbuilder_ng\Administrator\Controller;

\defined('_JEXEC') or die('Restricted access');

use CB\Component\Contentbuilder_ng\Administrator\Helper\DatabaseAuditHelper;
use CB\Component\Contentbuilder_ng\Administrator\Helper\PackedDataMigrationHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Route;

final class AboutController extends BaseController
{
    protected $default_view = 'about';

    public function migratePackedData(): void
    {
        $this->checkToken();

        $app = Factory::getApplication();
        $user = $app->getIdentity();

        if (!$user->authorise('core.manage', 'com_contentbuilder_ng')) {
            throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        try {
            $summary = PackedDataMigrationHelper::migrate();
            $scanned = (int) ($summary['scanned'] ?? 0);
            $candidates = (int) ($summary['candidates'] ?? 0);
            $migrated = (int) ($summary['migrated'] ?? 0);
            $unchanged = (int) ($summary['unchanged'] ?? 0);
            $errors = (int) ($summary['errors'] ?? 0);

            $repair = is_array($summary['repair'] ?? null) ? $summary['repair'] : [];
            $repairSupported = (bool) ($repair['supported'] ?? false);
            $repairScanned = (int) ($repair['scanned'] ?? 0);
            $repairConverted = (int) ($repair['converted'] ?? 0);
            $repairUnchanged = (int) ($repair['unchanged'] ?? 0);
            $repairErrors = (int) ($repair['errors'] ?? 0);
            $repairTarget = (string) ($repair['target_collation'] ?? 'utf8mb4_0900_ai_ci');
            $repairWarnings = is_array($repair['warnings'] ?? null) ? $repair['warnings'] : [];
            $auditColumns = is_array($summary['audit_columns'] ?? null) ? $summary['audit_columns'] : [];
            $auditScanned = (int) ($auditColumns['scanned'] ?? 0);
            $auditIssues = (int) ($auditColumns['issues'] ?? 0);
            $auditRepaired = (int) ($auditColumns['repaired'] ?? 0);
            $auditUnchanged = (int) ($auditColumns['unchanged'] ?? 0);
            $auditErrors = (int) ($auditColumns['errors'] ?? 0);
            $auditWarnings = is_array($auditColumns['warnings'] ?? null) ? $auditColumns['warnings'] : [];

            if (
                $migrated === 0
                && $errors === 0
                && $repairSupported
                && $repairConverted === 0
                && $repairErrors === 0
                && $auditIssues === 0
                && $auditErrors === 0
            ) {
                $message = Text::sprintf(
                    'COM_CONTENTBUILDER_NG_PACKED_MIGRATION_UP_TO_DATE',
                    $scanned,
                    $repairScanned
                );
                $message .= ' ' . Text::sprintf(
                    'COM_CONTENTBUILDER_NG_AUDIT_COLUMNS_REPAIR_UP_TO_DATE',
                    $auditScanned
                );
                $this->setMessage($message, 'message');
                $this->setRedirect(Route::_('index.php?option=com_contentbuilder_ng&view=about', false));

                return;
            }

            $message = Text::sprintf(
                'COM_CONTENTBUILDER_NG_PACKED_MIGRATION_SUMMARY',
                $scanned,
                $candidates,
                $migrated,
                $unchanged,
                $errors,
                $repairScanned,
                $repairConverted,
                $repairUnchanged,
                $repairErrors
            );
            $message .= ' ' . Text::sprintf(
                'COM_CONTENTBUILDER_NG_AUDIT_COLUMNS_REPAIR_SUMMARY',
                $auditScanned,
                $auditIssues,
                $auditRepaired,
                $auditUnchanged,
                $auditErrors
            );

            $tableMessages = [];
            $tables = $summary['tables'] ?? [];

            if (is_array($tables)) {
                foreach ($tables as $tableStat) {
                    if (!is_array($tableStat)) {
                        continue;
                    }

                    $tableMessages[] = Text::sprintf(
                        'COM_CONTENTBUILDER_NG_PACKED_MIGRATION_TABLE_SUMMARY',
                        (string) ($tableStat['table'] ?? ''),
                        (string) ($tableStat['column'] ?? ''),
                        (int) ($tableStat['scanned'] ?? 0),
                        (int) ($tableStat['candidates'] ?? 0),
                        (int) ($tableStat['migrated'] ?? 0),
                        (int) ($tableStat['unchanged'] ?? 0),
                        (int) ($tableStat['errors'] ?? 0)
                    );
                }
            }

            $repairTables = $repair['tables'] ?? [];

            if (is_array($repairTables)) {
                foreach ($repairTables as $repairStat) {
                    if (!is_array($repairStat)) {
                        continue;
                    }

                    $status = (string) ($repairStat['status'] ?? '');
                    $table = (string) ($repairStat['table'] ?? '');
                    $from = (string) ($repairStat['from'] ?? '');
                    $to = (string) ($repairStat['to'] ?? $repairTarget);
                    $errorMessage = (string) ($repairStat['error'] ?? '');

                    if ($from === '') {
                        $from = Text::_('COM_CONTENTBUILDER_NG_NOT_AVAILABLE');
                    }

                    if ($status === 'converted') {
                        $tableMessages[] = Text::sprintf(
                            'COM_CONTENTBUILDER_NG_COLLATION_REPAIR_TABLE_CONVERTED',
                            $table,
                            $from,
                            $to
                        );
                        continue;
                    }

                    if ($status === 'error') {
                        $tableMessages[] = Text::sprintf(
                            'COM_CONTENTBUILDER_NG_COLLATION_REPAIR_TABLE_ERROR',
                            $table,
                            $from,
                            $to,
                            $errorMessage
                        );
                    }
                }
            }

            $auditTables = $auditColumns['tables'] ?? [];

            if (is_array($auditTables)) {
                foreach ($auditTables as $auditTable) {
                    if (!is_array($auditTable)) {
                        continue;
                    }

                    $status = (string) ($auditTable['status'] ?? '');
                    $table = (string) ($auditTable['table'] ?? '');
                    $missing = (array) ($auditTable['missing'] ?? []);
                    $added = (array) ($auditTable['added'] ?? []);
                    $errorMessage = (string) ($auditTable['error'] ?? '');

                    if ($status !== 'repaired' && $status !== 'partial' && $status !== 'error') {
                        continue;
                    }

                    $missingLabel = $missing !== [] ? implode(', ', $missing) : Text::_('COM_CONTENTBUILDER_NG_NOT_AVAILABLE');
                    $addedLabel = $added !== [] ? implode(', ', $added) : Text::_('COM_CONTENTBUILDER_NG_NOT_AVAILABLE');

                    if ($status === 'repaired') {
                        $tableMessages[] = Text::sprintf(
                            'COM_CONTENTBUILDER_NG_AUDIT_COLUMNS_REPAIR_TABLE_REPAIRED',
                            $table,
                            $missingLabel,
                            $addedLabel
                        );
                        continue;
                    }

                    if ($status === 'partial') {
                        $tableMessages[] = Text::sprintf(
                            'COM_CONTENTBUILDER_NG_AUDIT_COLUMNS_REPAIR_TABLE_PARTIAL',
                            $table,
                            $missingLabel,
                            $addedLabel,
                            $errorMessage
                        );
                        continue;
                    }

                    $tableMessages[] = Text::sprintf(
                        'COM_CONTENTBUILDER_NG_AUDIT_COLUMNS_REPAIR_TABLE_ERROR',
                        $table,
                        $missingLabel,
                        $errorMessage
                    );
                }
            }

            if (!$repairSupported) {
                $tableMessages[] = Text::sprintf(
                    'COM_CONTENTBUILDER_NG_COLLATION_REPAIR_UNSUPPORTED',
                    $repairTarget
                );
            }

            foreach ($repairWarnings as $repairWarning) {
                $repairWarning = trim((string) $repairWarning);

                if ($repairWarning === '') {
                    continue;
                }

                $tableMessages[] = Text::sprintf(
                    'COM_CONTENTBUILDER_NG_COLLATION_REPAIR_WARNING',
                    $repairWarning
                );
            }

            foreach ($auditWarnings as $auditWarning) {
                $auditWarning = trim((string) $auditWarning);

                if ($auditWarning === '') {
                    continue;
                }

                $tableMessages[] = Text::sprintf(
                    'COM_CONTENTBUILDER_NG_AUDIT_COLUMNS_REPAIR_WARNING',
                    $auditWarning
                );
            }

            if ($tableMessages !== []) {
                $message .= '<br>' . implode('<br>', $tableMessages);
            }

            $level = ($errors > 0 || $repairErrors > 0 || !$repairSupported || $auditErrors > 0)
                ? 'warning'
                : 'message';
            $this->setMessage($message, $level);
        } catch (\Throwable $e) {
            $this->setMessage(
                Text::sprintf('COM_CONTENTBUILDER_NG_PACKED_MIGRATION_FAILED', $e->getMessage()),
                'error'
            );
        }

        $this->setRedirect(Route::_('index.php?option=com_contentbuilder_ng&view=about', false));
    }

    public function runAudit(): void
    {
        $this->checkToken();

        $app = Factory::getApplication();
        $user = $app->getIdentity();

        if (!$user->authorise('core.manage', 'com_contentbuilder_ng')) {
            throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        try {
            $report = DatabaseAuditHelper::run();
            $app->setUserState('com_contentbuilder_ng.about.audit', $report);

            $issuesTotal = (int) ($report['summary']['issues_total'] ?? 0);
            $scannedTables = (int) ($report['scanned_tables'] ?? 0);
            $errorsCount = count((array) ($report['errors'] ?? []));

            if ($issuesTotal === 0 && $errorsCount === 0) {
                $this->setMessage(
                    Text::sprintf('COM_CONTENTBUILDER_NG_ABOUT_AUDIT_SUMMARY_CLEAN', $scannedTables),
                    'message'
                );
            } else {
                $message = Text::sprintf(
                    'COM_CONTENTBUILDER_NG_ABOUT_AUDIT_SUMMARY_ISSUES',
                    $issuesTotal,
                    $scannedTables
                );

                if ($errorsCount > 0) {
                    $message .= ' ' . Text::sprintf('COM_CONTENTBUILDER_NG_ABOUT_AUDIT_SUMMARY_PARTIAL', $errorsCount);
                }

                $this->setMessage($message, 'warning');
            }
        } catch (\Throwable $e) {
            $this->setMessage(
                Text::sprintf('COM_CONTENTBUILDER_NG_ABOUT_AUDIT_FAILED', $e->getMessage()),
                'error'
            );
        }

        $this->setRedirect(Route::_('index.php?option=com_contentbuilder_ng&view=about', false));
    }
}
