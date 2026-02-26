<?php
/**
 * @package     ContentBuilder NG
 * @author      XDA+GIL
 * @link        https://breezingforms-ng.vcmb.fr
 * @copyright   Copyright © 2026 by XDA+GIL
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace CB\Component\Contentbuilderng\Administrator\Controller;

\defined('_JEXEC') or die('Restricted access');

use CB\Component\Contentbuilderng\Administrator\Helper\DatabaseAuditHelper;
use CB\Component\Contentbuilderng\Administrator\Helper\PackedDataMigrationHelper;
use Joomla\CMS\Application\AdministratorApplication;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Route;

final class AboutController extends BaseController
{
    protected $default_view = 'about';
    private const ABOUT_LOG_FILES = [
        'contentbuilderng_install.log',
        'com_contentbuilderng.admin.log',
        'com_contentbuilderng.site.log',
    ];
    private const ABOUT_LOG_TAIL_BYTES = 262144;

    public function migratePackedData(): void
    {
        $this->checkToken();

        $app = Factory::getApplication();
        $user = $app->getIdentity();

        if (!$user->authorise('core.manage', 'com_contentbuilderng')) {
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
            $pluginDuplicates = is_array($summary['plugin_duplicates'] ?? null) ? $summary['plugin_duplicates'] : [];
            $pluginDuplicateScanned = (int) ($pluginDuplicates['scanned'] ?? 0);
            $pluginDuplicateIssues = (int) ($pluginDuplicates['issues'] ?? 0);
            $pluginDuplicateRepaired = (int) ($pluginDuplicates['repaired'] ?? 0);
            $pluginDuplicateUnchanged = (int) ($pluginDuplicates['unchanged'] ?? 0);
            $pluginDuplicateErrors = (int) ($pluginDuplicates['errors'] ?? 0);
            $pluginDuplicateRowsRemoved = (int) ($pluginDuplicates['rows_removed'] ?? 0);
            $pluginDuplicateWarnings = is_array($pluginDuplicates['warnings'] ?? null)
                ? $pluginDuplicates['warnings']
                : [];
            $legacyMenuEntries = is_array($summary['legacy_menu_entries'] ?? null) ? $summary['legacy_menu_entries'] : [];
            $legacyMenuScanned = (int) ($legacyMenuEntries['scanned'] ?? 0);
            $legacyMenuIssues = (int) ($legacyMenuEntries['issues'] ?? 0);
            $legacyMenuRepaired = (int) ($legacyMenuEntries['repaired'] ?? 0);
            $legacyMenuUnchanged = (int) ($legacyMenuEntries['unchanged'] ?? 0);
            $legacyMenuErrors = (int) ($legacyMenuEntries['errors'] ?? 0);
            $legacyMenuWarnings = is_array($legacyMenuEntries['warnings'] ?? null)
                ? $legacyMenuEntries['warnings']
                : [];

            if (
                $migrated === 0
                && $errors === 0
                && $repairSupported
                && $repairConverted === 0
                && $repairErrors === 0
                && $auditIssues === 0
                && $auditErrors === 0
                && $pluginDuplicateIssues === 0
                && $pluginDuplicateErrors === 0
                && $legacyMenuIssues === 0
                && $legacyMenuErrors === 0
            ) {
                $message = Text::sprintf(
                    'COM_CONTENTBUILDERNG_PACKED_MIGRATION_UP_TO_DATE',
                    $scanned,
                    $repairScanned
                );
                $message .= ' ' . Text::sprintf(
                    'COM_CONTENTBUILDERNG_AUDIT_COLUMNS_REPAIR_UP_TO_DATE',
                    $auditScanned
                );
                $message .= ' ' . Text::sprintf(
                    'COM_CONTENTBUILDERNG_PLUGIN_DUPLICATES_REPAIR_UP_TO_DATE',
                    $pluginDuplicateScanned
                );
                $message .= ' ' . Text::sprintf(
                    'COM_CONTENTBUILDERNG_LEGACY_MENU_REPAIR_UP_TO_DATE',
                    $legacyMenuScanned
                );
                $this->setMessage($message, 'message');
                $this->setRedirect(Route::_('index.php?option=com_contentbuilderng&view=about', false));

                return;
            }

            $message = Text::sprintf(
                'COM_CONTENTBUILDERNG_PACKED_MIGRATION_SUMMARY',
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
                'COM_CONTENTBUILDERNG_AUDIT_COLUMNS_REPAIR_SUMMARY',
                $auditScanned,
                $auditIssues,
                $auditRepaired,
                $auditUnchanged,
                $auditErrors
            );
            $message .= ' ' . Text::sprintf(
                'COM_CONTENTBUILDERNG_PLUGIN_DUPLICATES_REPAIR_SUMMARY',
                $pluginDuplicateScanned,
                $pluginDuplicateIssues,
                $pluginDuplicateRepaired,
                $pluginDuplicateUnchanged,
                $pluginDuplicateRowsRemoved,
                $pluginDuplicateErrors
            );
            $message .= ' ' . Text::sprintf(
                'COM_CONTENTBUILDERNG_LEGACY_MENU_REPAIR_SUMMARY',
                $legacyMenuScanned,
                $legacyMenuIssues,
                $legacyMenuRepaired,
                $legacyMenuUnchanged,
                $legacyMenuErrors
            );

            $tableMessages = [];
            $tables = $summary['tables'] ?? [];

            if (is_array($tables)) {
                foreach ($tables as $tableStat) {
                    if (!is_array($tableStat)) {
                        continue;
                    }

                    $tableMessages[] = Text::sprintf(
                        'COM_CONTENTBUILDERNG_PACKED_MIGRATION_TABLE_SUMMARY',
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
                        $from = Text::_('COM_CONTENTBUILDERNG_NOT_AVAILABLE');
                    }

                    if ($status === 'converted') {
                        $tableMessages[] = Text::sprintf(
                            'COM_CONTENTBUILDERNG_COLLATION_REPAIR_TABLE_CONVERTED',
                            $table,
                            $from,
                            $to
                        );
                        continue;
                    }

                    if ($status === 'error') {
                        $tableMessages[] = Text::sprintf(
                            'COM_CONTENTBUILDERNG_COLLATION_REPAIR_TABLE_ERROR',
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

                    $missingLabel = $missing !== [] ? implode(', ', $missing) : Text::_('COM_CONTENTBUILDERNG_NOT_AVAILABLE');
                    $addedLabel = $added !== [] ? implode(', ', $added) : Text::_('COM_CONTENTBUILDERNG_NOT_AVAILABLE');

                    if ($status === 'repaired') {
                        $tableMessages[] = Text::sprintf(
                            'COM_CONTENTBUILDERNG_AUDIT_COLUMNS_REPAIR_TABLE_REPAIRED',
                            $table,
                            $missingLabel,
                            $addedLabel
                        );
                        continue;
                    }

                    if ($status === 'partial') {
                        $tableMessages[] = Text::sprintf(
                            'COM_CONTENTBUILDERNG_AUDIT_COLUMNS_REPAIR_TABLE_PARTIAL',
                            $table,
                            $missingLabel,
                            $addedLabel,
                            $errorMessage
                        );
                        continue;
                    }

                    $tableMessages[] = Text::sprintf(
                        'COM_CONTENTBUILDERNG_AUDIT_COLUMNS_REPAIR_TABLE_ERROR',
                        $table,
                        $missingLabel,
                        $errorMessage
                    );
                }
            }

            $pluginDuplicateGroups = $pluginDuplicates['groups'] ?? [];
            if (is_array($pluginDuplicateGroups)) {
                foreach ($pluginDuplicateGroups as $pluginDuplicateGroup) {
                    if (!is_array($pluginDuplicateGroup)) {
                        continue;
                    }

                    $status = (string) ($pluginDuplicateGroup['status'] ?? '');
                    $canonicalFolder = trim((string) ($pluginDuplicateGroup['canonical_folder'] ?? ''));
                    $canonicalElement = trim((string) ($pluginDuplicateGroup['canonical_element'] ?? ''));
                    $canonicalLabel = $canonicalFolder !== '' || $canonicalElement !== ''
                        ? $canonicalFolder . '/' . $canonicalElement
                        : Text::_('COM_CONTENTBUILDERNG_NOT_AVAILABLE');
                    $keepId = (int) ($pluginDuplicateGroup['keep_id'] ?? 0);
                    $removedIds = array_values(array_map(
                        static fn($id): int => (int) $id,
                        (array) ($pluginDuplicateGroup['removed_ids'] ?? [])
                    ));
                    $removedLabel = $removedIds !== []
                        ? implode(', ', $removedIds)
                        : Text::_('COM_CONTENTBUILDERNG_NOT_AVAILABLE');
                    $errorMessage = trim((string) ($pluginDuplicateGroup['error'] ?? ''));

                    if ($status === 'repaired') {
                        $tableMessages[] = Text::sprintf(
                            'COM_CONTENTBUILDERNG_PLUGIN_DUPLICATES_REPAIR_GROUP_REPAIRED',
                            $canonicalLabel,
                            $keepId,
                            $removedLabel
                        );
                        continue;
                    }

                    if ($status === 'error') {
                        $tableMessages[] = Text::sprintf(
                            'COM_CONTENTBUILDERNG_PLUGIN_DUPLICATES_REPAIR_GROUP_ERROR',
                            $canonicalLabel,
                            $keepId,
                            $removedLabel,
                            $errorMessage
                        );
                    }
                }
            }

            $legacyMenuRows = $legacyMenuEntries['entries'] ?? [];
            if (is_array($legacyMenuRows)) {
                foreach ($legacyMenuRows as $legacyMenuRow) {
                    if (!is_array($legacyMenuRow)) {
                        continue;
                    }

                    $status = (string) ($legacyMenuRow['status'] ?? '');
                    $menuId = (int) ($legacyMenuRow['menu_id'] ?? 0);
                    $oldTitle = trim((string) ($legacyMenuRow['old_title'] ?? ''));
                    $newTitle = trim((string) ($legacyMenuRow['new_title'] ?? ''));
                    $errorMessage = trim((string) ($legacyMenuRow['error'] ?? ''));

                    if ($status === 'repaired') {
                        $tableMessages[] = Text::sprintf(
                            'COM_CONTENTBUILDERNG_LEGACY_MENU_REPAIR_ENTRY_REPAIRED',
                            $menuId,
                            $oldTitle !== '' ? $oldTitle : Text::_('COM_CONTENTBUILDERNG_NOT_AVAILABLE'),
                            $newTitle !== '' ? $newTitle : Text::_('COM_CONTENTBUILDERNG_NOT_AVAILABLE')
                        );
                        continue;
                    }

                    if ($status === 'error') {
                        $tableMessages[] = Text::sprintf(
                            'COM_CONTENTBUILDERNG_LEGACY_MENU_REPAIR_ENTRY_ERROR',
                            $menuId,
                            $oldTitle !== '' ? $oldTitle : Text::_('COM_CONTENTBUILDERNG_NOT_AVAILABLE'),
                            $newTitle !== '' ? $newTitle : Text::_('COM_CONTENTBUILDERNG_NOT_AVAILABLE'),
                            $errorMessage
                        );
                    }
                }
            }

            if (!$repairSupported) {
                $tableMessages[] = Text::sprintf(
                    'COM_CONTENTBUILDERNG_COLLATION_REPAIR_UNSUPPORTED',
                    $repairTarget
                );
            }

            foreach ($repairWarnings as $repairWarning) {
                $repairWarning = trim((string) $repairWarning);

                if ($repairWarning === '') {
                    continue;
                }

                $tableMessages[] = Text::sprintf(
                    'COM_CONTENTBUILDERNG_COLLATION_REPAIR_WARNING',
                    $repairWarning
                );
            }

            foreach ($auditWarnings as $auditWarning) {
                $auditWarning = trim((string) $auditWarning);

                if ($auditWarning === '') {
                    continue;
                }

                $tableMessages[] = Text::sprintf(
                    'COM_CONTENTBUILDERNG_AUDIT_COLUMNS_REPAIR_WARNING',
                    $auditWarning
                );
            }

            foreach ($pluginDuplicateWarnings as $pluginDuplicateWarning) {
                $pluginDuplicateWarning = trim((string) $pluginDuplicateWarning);

                if ($pluginDuplicateWarning === '') {
                    continue;
                }

                $tableMessages[] = Text::sprintf(
                    'COM_CONTENTBUILDERNG_PLUGIN_DUPLICATES_REPAIR_WARNING',
                    $pluginDuplicateWarning
                );
            }

            foreach ($legacyMenuWarnings as $legacyMenuWarning) {
                $legacyMenuWarning = trim((string) $legacyMenuWarning);

                if ($legacyMenuWarning === '') {
                    continue;
                }

                $tableMessages[] = Text::sprintf(
                    'COM_CONTENTBUILDERNG_LEGACY_MENU_REPAIR_WARNING',
                    $legacyMenuWarning
                );
            }

            if ($tableMessages !== []) {
                $message .= '<br>' . implode('<br>', $tableMessages);
            }

            $level = (
                $errors > 0
                || $repairErrors > 0
                || !$repairSupported
                || $auditErrors > 0
                || $pluginDuplicateErrors > 0
                || $legacyMenuErrors > 0
            )
                ? 'warning'
                : 'message';
            $this->setMessage($message, $level);
        } catch (\Throwable $e) {
            $this->setMessage(
                Text::sprintf('COM_CONTENTBUILDERNG_PACKED_MIGRATION_FAILED', $e->getMessage()),
                'error'
            );
        }

        $this->setRedirect(Route::_('index.php?option=com_contentbuilderng&view=about', false));
    }

    public function runAudit(): void
    {
        $this->checkToken();

        /** @var AdministratorApplication $app */
        $app = Factory::getApplication();
        $user = $app->getIdentity();

        if (!$user->authorise('core.manage', 'com_contentbuilderng')) {
            throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        try {
            $report = DatabaseAuditHelper::run();
            $app->setUserState('com_contentbuilderng.about.audit', $report);

            $issuesTotal = (int) ($report['summary']['issues_total'] ?? 0);
            $scannedTables = (int) ($report['scanned_tables'] ?? 0);
            $errorsCount = count((array) ($report['errors'] ?? []));

            if ($issuesTotal === 0 && $errorsCount === 0) {
                $this->setMessage(
                    Text::sprintf('COM_CONTENTBUILDERNG_ABOUT_AUDIT_SUMMARY_CLEAN', $scannedTables),
                    'message'
                );
            } else {
                $message = Text::sprintf(
                    'COM_CONTENTBUILDERNG_ABOUT_AUDIT_SUMMARY_ISSUES',
                    $issuesTotal,
                    $scannedTables
                );

                if ($errorsCount > 0) {
                    $message .= ' ' . Text::sprintf('COM_CONTENTBUILDERNG_ABOUT_AUDIT_SUMMARY_PARTIAL', $errorsCount);
                }

                $this->setMessage($message, 'warning');
            }
        } catch (\Throwable $e) {
            $this->setMessage(
                Text::sprintf('COM_CONTENTBUILDERNG_ABOUT_AUDIT_FAILED', $e->getMessage()),
                'error'
            );
        }

        $this->setRedirect(Route::_('index.php?option=com_contentbuilderng&view=about', false));
    }

    public function showLog(): void
    {
        $this->checkToken();

        /** @var AdministratorApplication $app */
        $app = Factory::getApplication();
        $user = $app->getIdentity();

        if (!$user->authorise('core.manage', 'com_contentbuilderng')) {
            throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        try {
            $logReport = $this->readAboutLogReport();
            $app->setUserState('com_contentbuilderng.about.log', $logReport);

            $this->setMessage(
                Text::sprintf('COM_CONTENTBUILDERNG_ABOUT_LOG_LOADED', (string) ($logReport['file'] ?? '')),
                'message'
            );
        } catch (\Throwable $e) {
            $app->setUserState('com_contentbuilderng.about.log', []);

            $this->setMessage(
                Text::sprintf('COM_CONTENTBUILDERNG_ABOUT_LOG_LOAD_FAILED', $e->getMessage()),
                'error'
            );
        }

        $this->setRedirect(Route::_('index.php?option=com_contentbuilderng&view=about', false));
    }

    private function readAboutLogReport(): array
    {
        $logDirectory = $this->resolveLogDirectory();
        $logFile = $this->resolveLogFile($logDirectory);

        if ($logFile === null) {
            throw new \RuntimeException(Text::sprintf('COM_CONTENTBUILDERNG_ABOUT_LOG_NOT_FOUND', $logDirectory));
        }

        $size = is_file($logFile) ? (int) @filesize($logFile) : 0;
        $tailBytes = self::ABOUT_LOG_TAIL_BYTES;
        $truncated = false;
        $content = $this->readLogTail($logFile, $tailBytes, $truncated);

        return [
            'file' => basename($logFile),
            'path' => $logFile,
            'size' => max(0, $size),
            'content' => $content,
            'loaded_at' => Factory::getDate()->format('Y-m-d H:i:s'),
            'truncated' => $truncated ? 1 : 0,
            'tail_bytes' => $tailBytes,
        ];
    }

    private function resolveLogDirectory(): string
    {
        $app = Factory::getApplication();
        $configuredPath = '';

        if (is_object($app) && method_exists($app, 'get')) {
            $configuredPath = trim((string) $app->get('log_path', ''));
        }

        if ($configuredPath === '') {
            $configuredPath = JPATH_ROOT . '/logs';
        }

        return rtrim($configuredPath, '/\\');
    }

    private function resolveLogFile(string $logDirectory): ?string
    {
        foreach (self::ABOUT_LOG_FILES as $fileName) {
            $path = $logDirectory . '/' . $fileName;

            if (is_file($path) && is_readable($path)) {
                return $path;
            }
        }

        return null;
    }

    private function readLogTail(string $logFile, int $tailBytes, bool &$truncated): string
    {
        $truncated = false;
        $size = (int) @filesize($logFile);
        $tailBytes = max(1, $tailBytes);
        $handle = @fopen($logFile, 'rb');

        if (!is_resource($handle)) {
            throw new \RuntimeException('Unable to open log file for reading.');
        }

        try {
            if ($size > $tailBytes) {
                $truncated = true;
                fseek($handle, -$tailBytes, SEEK_END);
            }

            $data = stream_get_contents($handle);
        } finally {
            fclose($handle);
        }

        if (!is_string($data)) {
            throw new \RuntimeException('Unable to read log file content.');
        }

        if ($truncated) {
            $lineBreakPosition = strpos($data, "\n");

            if ($lineBreakPosition !== false) {
                $data = substr($data, $lineBreakPosition + 1);
            }
        }

        return trim($data);
    }
}
