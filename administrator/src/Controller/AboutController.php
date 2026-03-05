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
use Joomla\Database\DatabaseInterface;

final class AboutController extends BaseController
{
    protected $default_view = 'about';
    private const CONFIG_TRANSFER_SELECTION_STATE_KEY = 'com_contentbuilderng.configtransfer.selection';
    private const ABOUT_LOG_FILES = [
        'com_contentbuilderng.log',
    ];
    private const ABOUT_LOG_TAIL_BYTES = 262144;
    private const CONFIG_IMPORT_MODE_MERGE = 'merge';
    private const CONFIG_IMPORT_MODE_REPLACE = 'replace';
    private const CONFIG_EXPORT_SECTIONS = [
        'component_params' => ['type' => 'component_params'],
        'forms' => ['type' => 'table', 'table' => '#__contentbuilderng_forms'],
        'elements' => ['type' => 'table', 'table' => '#__contentbuilderng_elements'],
        'list_states' => ['type' => 'table', 'table' => '#__contentbuilderng_list_states'],
        'storages' => ['type' => 'table', 'table' => '#__contentbuilderng_storages'],
        'storage_fields' => ['type' => 'table', 'table' => '#__contentbuilderng_storage_fields'],
        'resource_access' => ['type' => 'table', 'table' => '#__contentbuilderng_resource_access'],
    ];

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

    public function exportConfiguration(): void
    {
        $this->checkToken();

        /** @var AdministratorApplication $app */
        $app = Factory::getApplication();
        $user = $app->getIdentity();

        if (!$user->authorise('core.manage', 'com_contentbuilderng')) {
            throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        $this->rememberConfigTransferSelection();

        try {
            $selectedSections = $this->getSelectedConfigSections();
            if ($selectedSections === []) {
                throw new \RuntimeException(Text::_('COM_CONTENTBUILDERNG_ABOUT_CONFIGURATION_SELECT_SECTION'));
            }

            $selectedFormIds = $this->getSelectedConfigFormIds();
            $selectedStorageIds = $this->getSelectedConfigStorageIds();
            $requireFormFilter = $app->input->getInt('cb_require_form_filter', 0) === 1;
            $requireStorageFilter = $app->input->getInt('cb_require_storage_filter', 0) === 1;

            if (
                $requireFormFilter
                && $selectedFormIds === []
                && array_intersect($selectedSections, ['forms', 'elements', 'list_states', 'resource_access']) !== []
            ) {
                throw new \RuntimeException(Text::_('COM_CONTENTBUILDERNG_ABOUT_CONFIGURATION_SELECT_FORM'));
            }

            if (
                $requireStorageFilter
                && $selectedStorageIds === []
                && array_intersect($selectedSections, ['storages', 'storage_fields']) !== []
            ) {
                throw new \RuntimeException(Text::_('COM_CONTENTBUILDERNG_ABOUT_CONFIGURATION_SELECT_STORAGE'));
            }

            $payload = $this->buildConfigurationExportPayload($selectedSections, $selectedFormIds, $selectedStorageIds);
            $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            if (!is_string($json) || $json === '') {
                throw new \RuntimeException('Failed to encode configuration export payload.');
            }

            $fileName = 'contentbuilderng-config-' . Factory::getDate()->format('Ymd-His') . '.json';

            $app->setHeader('Content-Type', 'application/json; charset=utf-8', true);
            $app->setHeader('Content-Disposition', 'attachment; filename="' . $fileName . '"', true);
            $app->setHeader('Pragma', 'no-cache', true);
            $app->setHeader('Expires', '0', true);
            $app->sendHeaders();
            echo $json;
            $app->close();
        } catch (\Throwable $e) {
            $this->setMessage(
                Text::sprintf('COM_CONTENTBUILDERNG_ABOUT_EXPORT_CONFIGURATION_FAILED', $e->getMessage()),
                'error'
            );
            $this->setRedirect($this->buildConfigTransferRedirect('export'));
        }
    }

    public function importConfiguration(): void
    {
        $this->checkToken();

        /** @var AdministratorApplication $app */
        $app = Factory::getApplication();
        $user = $app->getIdentity();

        if (!$user->authorise('core.manage', 'com_contentbuilderng')) {
            throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        $this->rememberConfigTransferSelection();

        try {
            $selectedSections = $this->getSelectedConfigSections();
            if ($selectedSections === []) {
                throw new \RuntimeException(Text::_('COM_CONTENTBUILDERNG_ABOUT_CONFIGURATION_SELECT_SECTION'));
            }
            $importMode = $this->getImportMode();

            $upload = (array) $app->input->files->get('cb_config_import_file', [], 'array');
            $tmpName = (string) ($upload['tmp_name'] ?? '');
            $errorCode = (int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE);

            if ($errorCode !== UPLOAD_ERR_OK || $tmpName === '' || !is_uploaded_file($tmpName)) {
                throw new \RuntimeException(Text::_('COM_CONTENTBUILDERNG_ABOUT_IMPORT_CONFIGURATION_SELECT_FILE'));
            }

            $raw = file_get_contents($tmpName);
            if (!is_string($raw) || trim($raw) === '') {
                throw new \RuntimeException(Text::_('COM_CONTENTBUILDERNG_ABOUT_IMPORT_CONFIGURATION_INVALID'));
            }

            $payload = json_decode($raw, true);
            if (!is_array($payload)) {
                throw new \RuntimeException(Text::_('COM_CONTENTBUILDERNG_ABOUT_IMPORT_CONFIGURATION_INVALID'));
            }

            $format = (string) ($payload['meta']['format'] ?? '');
            if ($format !== '' && $format !== 'cbng-config-export-v1') {
                throw new \RuntimeException(Text::_('COM_CONTENTBUILDERNG_ABOUT_IMPORT_CONFIGURATION_INVALID'));
            }

            $summary = $this->applyConfigurationImportPayload($payload, $selectedSections, $importMode);
            $app->setUserState('com_contentbuilderng.about.import', [
                'generated_at' => Factory::getDate()->format('Y-m-d H:i:s'),
                'summary' => $summary,
            ]);

            $this->setMessage(
                Text::sprintf(
                    'COM_CONTENTBUILDERNG_ABOUT_IMPORT_CONFIGURATION_SUCCESS',
                    (int) ($summary['tables'] ?? 0),
                    (int) ($summary['rows'] ?? 0)
                ),
                'message'
            );
        } catch (\Throwable $e) {
            $app->setUserState('com_contentbuilderng.about.import', [
                'generated_at' => Factory::getDate()->format('Y-m-d H:i:s'),
                'summary' => [
                    'status' => 'error',
                    'details' => [(string) $e->getMessage()],
                ],
            ]);
            $this->setMessage(
                Text::sprintf('COM_CONTENTBUILDERNG_ABOUT_IMPORT_CONFIGURATION_FAILED', $e->getMessage()),
                'error'
            );
        }

        $this->setRedirect($this->buildConfigTransferRedirect('import'));
    }

    private function getSelectedConfigSections(): array
    {
        $selectedRaw = (array) Factory::getApplication()->input->get('cb_config_sections', [], 'array');
        $selected = [];

        foreach ($selectedRaw as $sectionKey) {
            $key = strtolower((string) $sectionKey);
            $key = preg_replace('/[^a-z0-9_]/', '', $key) ?? '';

            if ($key !== '') {
                $selected[] = $key;
            }
        }

        $selected = array_values(array_unique($selected));
        $allowed = array_keys(self::CONFIG_EXPORT_SECTIONS);

        return array_values(array_intersect($selected, $allowed));
    }

    private function buildConfigurationExportPayload(array $selectedSections, array $selectedFormIds, array $selectedStorageIds): array
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $existingTables = array_map('strtolower', (array) $db->getTableList());
        $exportSections = [];

        foreach ($selectedSections as $sectionKey) {
            $sectionConfig = self::CONFIG_EXPORT_SECTIONS[$sectionKey] ?? null;
            if (!is_array($sectionConfig)) {
                continue;
            }

            if (($sectionConfig['type'] ?? '') === 'component_params') {
                $exportSections[$sectionKey] = [
                    'type' => 'component_params',
                    'params' => $this->loadComponentParams($db),
                ];
                continue;
            }

            $tableAlias = (string) ($sectionConfig['table'] ?? '');
            if ($tableAlias === '') {
                continue;
            }
            $tableName = $db->replacePrefix($tableAlias);

            if (!in_array(strtolower($tableName), $existingTables, true)) {
                continue;
            }

            $columns = array_keys((array) $db->getTableColumns($tableAlias, false));
            $query = $db->getQuery(true)
                ->select('*')
                ->from($db->quoteName($tableAlias));

            if ($selectedFormIds !== []) {
                if ($sectionKey === 'forms' && in_array('id', $columns, true)) {
                    $query->where($db->quoteName('id') . ' IN (' . implode(',', array_map('intval', $selectedFormIds)) . ')');
                }

                if (in_array($sectionKey, ['elements', 'list_states', 'resource_access'], true) && in_array('form_id', $columns, true)) {
                    $query->where($db->quoteName('form_id') . ' IN (' . implode(',', array_map('intval', $selectedFormIds)) . ')');
                }
            }

            if ($selectedStorageIds !== []) {
                if ($sectionKey === 'storages' && in_array('id', $columns, true)) {
                    $query->where($db->quoteName('id') . ' IN (' . implode(',', array_map('intval', $selectedStorageIds)) . ')');
                }

                if ($sectionKey === 'storage_fields' && in_array('storage_id', $columns, true)) {
                    $query->where($db->quoteName('storage_id') . ' IN (' . implode(',', array_map('intval', $selectedStorageIds)) . ')');
                }
            }

            if (in_array('id', $columns, true)) {
                $query->order($db->quoteName('id') . ' ASC');
            }

            $db->setQuery($query);
            $rows = (array) $db->loadAssocList();

            $exportSections[$sectionKey] = [
                'type' => 'table',
                'table' => $tableAlias,
                'row_count' => count($rows),
                'rows' => $rows,
            ];
        }

        return [
            'meta' => [
                'generated_at' => Factory::getDate()->toSql(),
                'generated_by' => (int) (Factory::getApplication()->getIdentity()->id ?? 0),
                'component' => 'com_contentbuilderng',
                'format' => 'cbng-config-export-v1',
            ],
            'sections' => $selectedSections,
            'filters' => [
                'form_ids' => $selectedFormIds,
                'storage_ids' => $selectedStorageIds,
            ],
            'data' => $exportSections,
        ];
    }

    private function getSelectedConfigFormIds(): array
    {
        return $this->getSelectedNumericIds('cb_config_form_ids');
    }

    private function getSelectedConfigStorageIds(): array
    {
        return $this->getSelectedNumericIds('cb_config_storage_ids');
    }

    private function getSelectedNumericIds(string $inputKey): array
    {
        $selectedRaw = (array) Factory::getApplication()->input->get($inputKey, [], 'array');
        $selected = [];

        foreach ($selectedRaw as $selectedId) {
            $id = (int) $selectedId;
            if ($id > 0) {
                $selected[] = $id;
            }
        }

        return array_values(array_unique($selected));
    }

    private function buildConfigTransferRedirect(string $fallbackMode = 'export'): string
    {
        $app = Factory::getApplication();
        $returnView = $app->input->getCmd('return_view', '');
        $returnMode = $app->input->getCmd('return_mode', $fallbackMode);
        $returnMode = in_array($returnMode, ['export', 'import'], true) ? $returnMode : $fallbackMode;

        if ($returnView === 'configtransfer') {
            return Route::_('index.php?option=com_contentbuilderng&view=configtransfer&mode=' . $returnMode, false);
        }

        return Route::_('index.php?option=com_contentbuilderng&view=about', false);
    }

    private function rememberConfigTransferSelection(): void
    {
        /** @var AdministratorApplication $app */
        $app = Factory::getApplication();
        $postData = (array) $app->input->post->getArray();
        $previous = (array) $app->getUserState(self::CONFIG_TRANSFER_SELECTION_STATE_KEY, []);

        $sections = (array) ($previous['sections'] ?? []);
        if (array_key_exists('cb_config_sections', $postData)) {
            $sections = $this->normalizeInputStringArray((array) ($postData['cb_config_sections'] ?? []));
        }

        $formIds = (array) ($previous['form_ids'] ?? []);
        if (array_key_exists('cb_config_form_ids', $postData)) {
            $formIds = $this->normalizeInputIntArray((array) ($postData['cb_config_form_ids'] ?? []));
        }

        $storageIds = (array) ($previous['storage_ids'] ?? []);
        if (array_key_exists('cb_config_storage_ids', $postData)) {
            $storageIds = $this->normalizeInputIntArray((array) ($postData['cb_config_storage_ids'] ?? []));
        }

        $app->setUserState(self::CONFIG_TRANSFER_SELECTION_STATE_KEY, [
            'touched' => 1,
            'sections' => $sections,
            'form_ids' => $formIds,
            'storage_ids' => $storageIds,
        ]);
    }

    private function normalizeInputStringArray(array $values): array
    {
        $clean = [];

        foreach ($values as $value) {
            $key = strtolower((string) $value);
            $key = preg_replace('/[^a-z0-9_]/', '', $key) ?? '';
            if ($key !== '') {
                $clean[] = $key;
            }
        }

        return array_values(array_unique($clean));
    }

    private function normalizeInputIntArray(array $values): array
    {
        $clean = [];

        foreach ($values as $value) {
            $id = (int) $value;
            if ($id > 0) {
                $clean[] = $id;
            }
        }

        return array_values(array_unique($clean));
    }

    private function loadComponentParams(DatabaseInterface $db): array
    {
        $query = $db->getQuery(true)
            ->select($db->quoteName('params'))
            ->from($db->quoteName('#__extensions'))
            ->where($db->quoteName('type') . ' = ' . $db->quote('component'))
            ->where($db->quoteName('element') . ' = ' . $db->quote('com_contentbuilderng'));
        $db->setQuery($query);
        $rawParams = (string) $db->loadResult();

        if ($rawParams === '') {
            return [];
        }

        $decoded = json_decode($rawParams, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function getImportMode(): string
    {
        $mode = strtolower((string) Factory::getApplication()->input->getCmd('cb_config_import_mode', self::CONFIG_IMPORT_MODE_MERGE));
        return in_array($mode, [self::CONFIG_IMPORT_MODE_MERGE, self::CONFIG_IMPORT_MODE_REPLACE], true)
            ? $mode
            : self::CONFIG_IMPORT_MODE_MERGE;
    }

    private function applyConfigurationImportPayload(array $payload, array $selectedSections, string $importMode): array
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $tableRowsImported = 0;
        $tablesImported = 0;
        $details = [];

        $dataSections = is_array($payload['data'] ?? null) ? $payload['data'] : [];
        // Backward compatibility with older export format.
        if ($dataSections === []) {
            if (isset($payload['component_params']) && is_array($payload['component_params'])) {
                $dataSections['component_params'] = [
                    'type' => 'component_params',
                    'params' => $payload['component_params'],
                ];
            }

            $legacyTables = is_array($payload['tables'] ?? null) ? $payload['tables'] : [];
            foreach ($legacyTables as $tableEntry) {
                if (!is_array($tableEntry)) {
                    continue;
                }

                $legacyTableAlias = (string) ($tableEntry['table'] ?? '');
                $legacyRows = is_array($tableEntry['rows'] ?? null) ? $tableEntry['rows'] : [];
                foreach (self::CONFIG_EXPORT_SECTIONS as $sectionKey => $sectionConfig) {
                    if (($sectionConfig['type'] ?? '') !== 'table') {
                        continue;
                    }
                    if ((string) ($sectionConfig['table'] ?? '') === $legacyTableAlias) {
                        $dataSections[$sectionKey] = [
                            'type' => 'table',
                            'table' => $legacyTableAlias,
                            'rows' => $legacyRows,
                        ];
                    }
                }
            }
        }

        $db->transactionStart();

        try {
            foreach ($selectedSections as $sectionKey) {
                $sectionConfig = self::CONFIG_EXPORT_SECTIONS[$sectionKey] ?? null;
                if (!is_array($sectionConfig)) {
                    continue;
                }

                $sectionPayload = $dataSections[$sectionKey] ?? null;
                if (!is_array($sectionPayload)) {
                    $details[] = Text::sprintf('COM_CONTENTBUILDERNG_ABOUT_IMPORT_CONFIGURATION_DETAIL_SECTION_MISSING', $sectionKey);
                    continue;
                }

                if (($sectionConfig['type'] ?? '') === 'component_params') {
                    $params = is_array($sectionPayload['params'] ?? null) ? $sectionPayload['params'] : [];
                    $query = $db->getQuery(true)
                        ->update($db->quoteName('#__extensions'))
                        ->set($db->quoteName('params') . ' = ' . $db->quote(json_encode($params)))
                        ->where($db->quoteName('type') . ' = ' . $db->quote('component'))
                        ->where($db->quoteName('element') . ' = ' . $db->quote('com_contentbuilderng'));
                    $db->setQuery($query)->execute();
                    $details[] = Text::_('COM_CONTENTBUILDERNG_ABOUT_IMPORT_CONFIGURATION_DETAIL_PARAMS_UPDATED');
                    continue;
                }

                $tableAlias = (string) ($sectionConfig['table'] ?? '');
                $rows = is_array($sectionPayload['rows'] ?? null) ? $sectionPayload['rows'] : [];
                $importedRows = $this->importConfigTableRows($db, $tableAlias, $rows, $importMode);
                $tableRowsImported += $importedRows;
                $tablesImported++;
                $details[] = Text::sprintf(
                    'COM_CONTENTBUILDERNG_ABOUT_IMPORT_CONFIGURATION_DETAIL_TABLE_IMPORTED',
                    $tableAlias,
                    $importedRows
                );
            }

            $db->transactionCommit();
        } catch (\Throwable $e) {
            $db->transactionRollback();
            throw $e;
        }

        return [
            'status' => 'ok',
            'tables' => $tablesImported,
            'rows' => $tableRowsImported,
            'details' => $details,
        ];
    }

    private function importConfigTableRows(DatabaseInterface $db, string $tableAlias, array $rows, string $importMode): int
    {
        $columns = array_keys((array) $db->getTableColumns($tableAlias, false));

        if ($columns === []) {
            return 0;
        }

        if ($importMode === self::CONFIG_IMPORT_MODE_REPLACE) {
            $query = $db->getQuery(true)
                ->delete($db->quoteName($tableAlias));
            $db->setQuery($query)->execute();
        }

        $imported = 0;
        $hasIdColumn = in_array('id', $columns, true);

        foreach ($rows as $rowIndex => $row) {
            if (!is_array($row)) {
                continue;
            }

            $filtered = [];
            foreach ($columns as $columnName) {
                if (!array_key_exists($columnName, $row)) {
                    continue;
                }
                $filtered[$columnName] = $row[$columnName];
            }

            if ($filtered === []) {
                continue;
            }

            try {
                if ($importMode === self::CONFIG_IMPORT_MODE_MERGE && $hasIdColumn && array_key_exists('id', $filtered)) {
                    $rowId = (int) $filtered['id'];
                    if ($rowId > 0) {
                        $existsQuery = $db->getQuery(true)
                            ->select('1')
                            ->from($db->quoteName($tableAlias))
                            ->where($db->quoteName('id') . ' = ' . $rowId);
                        $db->setQuery($existsQuery, 0, 1);
                        $exists = (int) $db->loadResult() === 1;

                        if ($exists) {
                            $updateQuery = $db->getQuery(true)
                                ->update($db->quoteName($tableAlias));
                            $setCount = 0;

                            foreach ($filtered as $columnName => $value) {
                                if ($columnName === 'id') {
                                    continue;
                                }
                                $updateQuery->set(
                                    $db->quoteName($columnName) . ' = ' . ($value === null ? 'NULL' : $db->quote((string) $value))
                                );
                                $setCount++;
                            }

                            if ($setCount > 0) {
                                $updateQuery->where($db->quoteName('id') . ' = ' . $rowId);
                                $db->setQuery($updateQuery)->execute();
                            }
                            $imported++;
                            continue;
                        }
                    }
                }

                $insertQuery = $db->getQuery(true)
                    ->insert($db->quoteName($tableAlias))
                    ->columns(array_map([$db, 'quoteName'], array_keys($filtered)));

                $values = [];
                foreach ($filtered as $value) {
                    $values[] = $value === null ? 'NULL' : $db->quote((string) $value);
                }

                $insertQuery->values(implode(',', $values));
                $db->setQuery($insertQuery)->execute();
                $imported++;
            } catch (\Throwable $e) {
                throw new \RuntimeException(
                    Text::sprintf(
                        'COM_CONTENTBUILDERNG_ABOUT_IMPORT_CONFIGURATION_ROW_ERROR',
                        $tableAlias,
                        ((int) $rowIndex) + 1,
                        $e->getMessage()
                    )
                );
            }
        }

        return $imported;
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
