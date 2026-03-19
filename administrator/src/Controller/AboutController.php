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
use CB\Component\Contentbuilderng\Administrator\Helper\Logger;
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
    private const REPAIR_WORKFLOW_STATE_KEY = 'com_contentbuilderng.about.repair_workflow';
    private const REPAIR_WORKFLOW_STEPS = [
        'table_encoding',
        'packed_data',
        'audit_columns',
        'plugin_duplicates',
        'historical_menu_entries',
    ];
    private const CONFIG_IMPORT_MODE_MERGE = 'merge';
    private const CONFIG_IMPORT_MODE_REPLACE = 'replace';
    private const CONFIG_TRANSFER_ROOT_SECTIONS = [
        'forms',
        'storages',
    ];
    private const CONFIG_FORM_DEPENDENT_SECTIONS = [
        'elements',
        'list_states',
        'resource_access',
    ];
    private const CONFIG_STORAGE_DEPENDENT_SECTIONS = [
        'storage_fields',
    ];
    private const CONFIG_EXPORT_SECTIONS = [
        'component_params' => ['type' => 'component_params'],
        'forms' => ['type' => 'table', 'table' => '#__contentbuilderng_forms'],
        'elements' => ['type' => 'table', 'table' => '#__contentbuilderng_elements'],
        'list_states' => ['type' => 'table', 'table' => '#__contentbuilderng_list_states'],
        'storages' => ['type' => 'table', 'table' => '#__contentbuilderng_storages'],
        'storage_fields' => ['type' => 'table', 'table' => '#__contentbuilderng_storage_fields'],
        'resource_access' => ['type' => 'table', 'table' => '#__contentbuilderng_resource_access'],
        'storage_content' => ['type' => 'storage_content'],
    ];

    public function migratePackedData(): void
    {
        $this->startRepairWorkflow();
    }

    public function startRepairWorkflow(): void
    {
        $this->checkToken();

        $app = $this->getAuthorizedApplication();
        $app->setUserState(self::REPAIR_WORKFLOW_STATE_KEY, $this->createRepairWorkflowState());
        $this->setMessage(Text::_('COM_CONTENTBUILDERNG_DB_REPAIR_WORKFLOW_STARTED'), 'message');
        $this->setRedirect(Route::_('index.php?option=com_contentbuilderng&view=about', false));
    }

    public function executeRepairWorkflowStep(): void
    {
        $this->checkToken();

        $app = $this->getAuthorizedApplication();
        $workflow = $this->getRepairWorkflowState($app);

        if ($workflow === []) {
            $this->setMessage(Text::_('COM_CONTENTBUILDERNG_DB_REPAIR_WORKFLOW_MISSING'), 'warning');
            $this->setRedirect(Route::_('index.php?option=com_contentbuilderng&view=about', false));

            return;
        }

        $currentIndex = (int) ($workflow['current_step'] ?? 0);
        $steps = array_values((array) ($workflow['steps'] ?? []));
        $currentStep = $steps[$currentIndex] ?? null;
        $requestedStepId = trim((string) $this->input->post->get('repair_step', '', 'cmd'));
        $action = trim((string) $this->input->post->get('repair_action', '', 'cmd'));

        if (!is_array($currentStep) || $requestedStepId === '' || $requestedStepId !== (string) ($currentStep['id'] ?? '')) {
            $this->setMessage(Text::_('COM_CONTENTBUILDERNG_DB_REPAIR_WORKFLOW_INVALID_STEP'), 'warning');
            $this->setRedirect(Route::_('index.php?option=com_contentbuilderng&view=about', false));

            return;
        }

        if ($action !== 'apply' && $action !== 'skip') {
            $this->setMessage(Text::_('COM_CONTENTBUILDERNG_DB_REPAIR_WORKFLOW_INVALID_ACTION'), 'warning');
            $this->setRedirect(Route::_('index.php?option=com_contentbuilderng&view=about', false));

            return;
        }

        if ((string) ($currentStep['status'] ?? 'pending') !== 'pending') {
            $this->setMessage(Text::_('COM_CONTENTBUILDERNG_DB_REPAIR_WORKFLOW_ALREADY_DONE'), 'message');
            $this->setRedirect(Route::_('index.php?option=com_contentbuilderng&view=about', false));

            return;
        }

        try {
            if ($action === 'skip') {
                $result = [
                    'level' => 'message',
                    'summary' => Text::_('COM_CONTENTBUILDERNG_DB_REPAIR_WORKFLOW_SKIPPED_SUMMARY'),
                    'lines' => [Text::_('COM_CONTENTBUILDERNG_DB_REPAIR_WORKFLOW_SKIPPED_LOG')],
                ];
                $currentStep['status'] = 'skipped';
            } else {
                $result = $this->runRepairWorkflowStep($requestedStepId);
                $currentStep['status'] = 'done';
            }
        } catch (\Throwable $e) {
            $result = [
                'level' => 'error',
                'summary' => Text::sprintf('COM_CONTENTBUILDERNG_PACKED_MIGRATION_FAILED', $e->getMessage()),
                'lines' => [],
            ];
            $currentStep['status'] = 'done';
        }

        $currentStep['decision'] = $action;
        $currentStep['result'] = $result;
        $currentStep['completed_at'] = $this->getJoomlaLocalDateTime();
        $steps[$currentIndex] = $currentStep;
        $workflow['steps'] = $steps;
        if ($currentIndex >= count($steps) - 1) {
            $workflow['completed'] = true;
        }
        $workflow['updated_at'] = $currentStep['completed_at'];
        $app->setUserState(self::REPAIR_WORKFLOW_STATE_KEY, $workflow);

        $this->setMessage((string) ($result['summary'] ?? ''), (string) ($result['level'] ?? 'message'));
        $this->setRedirect(Route::_('index.php?option=com_contentbuilderng&view=about', false));
    }

    public function nextRepairWorkflowStep(): void
    {
        $this->checkToken();

        $app = $this->getAuthorizedApplication();
        $workflow = $this->getRepairWorkflowState($app);

        if ($workflow === []) {
            $this->setMessage(Text::_('COM_CONTENTBUILDERNG_DB_REPAIR_WORKFLOW_MISSING'), 'warning');
            $this->setRedirect(Route::_('index.php?option=com_contentbuilderng&view=about', false));

            return;
        }

        $currentIndex = (int) ($workflow['current_step'] ?? 0);
        $steps = array_values((array) ($workflow['steps'] ?? []));
        $currentStep = $steps[$currentIndex] ?? null;

        if (!is_array($currentStep) || (string) ($currentStep['status'] ?? 'pending') === 'pending') {
            $this->setMessage(Text::_('COM_CONTENTBUILDERNG_DB_REPAIR_WORKFLOW_COMPLETE_STEP_FIRST'), 'warning');
            $this->setRedirect(Route::_('index.php?option=com_contentbuilderng&view=about', false));

            return;
        }

        if ($currentIndex < count($steps) - 1) {
            $workflow['current_step'] = $currentIndex + 1;
            $workflow['updated_at'] = $this->getJoomlaLocalDateTime();
            $app->setUserState(self::REPAIR_WORKFLOW_STATE_KEY, $workflow);
            $this->setMessage(Text::_('COM_CONTENTBUILDERNG_DB_REPAIR_WORKFLOW_NEXT_STEP'), 'message');
        } else {
            $workflow['completed'] = true;
            $workflow['updated_at'] = $this->getJoomlaLocalDateTime();
            $app->setUserState(self::REPAIR_WORKFLOW_STATE_KEY, $workflow);
            $this->setMessage(Text::_('COM_CONTENTBUILDERNG_DB_REPAIR_WORKFLOW_FINISHED'), 'message');
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
        $selectedSections = [];
        $selectedFormIds = [];
        $selectedStorageIds = [];
        $includeStorageContent = false;

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
            $selectedSections = $this->resolveEffectiveExportSections($selectedSections, $selectedFormIds, $selectedStorageIds);

            if ($selectedSections === []) {
                throw new \RuntimeException(Text::_('COM_CONTENTBUILDERNG_ABOUT_CONFIGURATION_SELECT_EXPORT_TARGET'));
            }

            $includeStorageContent = $this->shouldExportStorageContent();
            $payload = $this->buildConfigurationExportPayload($selectedSections, $selectedFormIds, $selectedStorageIds, $includeStorageContent);
            $exportSummary = $this->buildConfigurationExportSummary($payload, $selectedSections, $selectedFormIds, $selectedStorageIds, $includeStorageContent);
            $app->setUserState('com_contentbuilderng.about.export', [
                'generated_at' => $this->getJoomlaLocalDateTime(),
                'summary' => $exportSummary,
            ]);
            $this->logConfigurationExportReport($payload, $selectedSections, $selectedFormIds, $selectedStorageIds);
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
            $app->setUserState('com_contentbuilderng.about.export', [
                'generated_at' => $this->getJoomlaLocalDateTime(),
                'summary' => [
                    'status' => 'error',
                    'details' => [(string) $e->getMessage()],
                ],
            ]);
            Logger::error('Configuration export failed', [
                'sections' => $selectedSections,
                'form_ids' => $selectedFormIds,
                'storage_ids' => $selectedStorageIds,
                'include_storage_content' => $includeStorageContent ? 1 : 0,
                'error' => $e->getMessage(),
            ]);
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
        $selectedSections = [];
        $importMode = self::CONFIG_IMPORT_MODE_MERGE;

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

            $payload = $this->filterConfigurationImportPayload(
                $payload,
                $selectedSections,
                $this->getSelectedConfigImportNames('cb_config_import_form_names'),
                $this->getSelectedConfigImportNames('cb_config_import_storage_names'),
                $this->getSelectedConfigImportNames('cb_config_import_storage_content_names')
            );

            $summary = $this->applyConfigurationImportPayload($payload, $selectedSections, $importMode);
            $app->setUserState('com_contentbuilderng.about.import', [
                'generated_at' => $this->getJoomlaLocalDateTime(),
                'summary' => $summary,
            ]);
            $this->logConfigurationImportReport($summary, $selectedSections, $importMode);

            $rowsImported = (int) ($summary['rows'] ?? 0);
            $tablesImported = (int) ($summary['tables'] ?? 0);
            $messageKey = $rowsImported > 0
                ? 'COM_CONTENTBUILDERNG_ABOUT_IMPORT_CONFIGURATION_SUCCESS'
                : 'COM_CONTENTBUILDERNG_ABOUT_IMPORT_CONFIGURATION_NO_CHANGES';

            $this->setMessage(
                Text::sprintf($messageKey, $tablesImported, $rowsImported),
                'message'
            );
        } catch (\Throwable $e) {
            $app->setUserState('com_contentbuilderng.about.import', [
                'generated_at' => $this->getJoomlaLocalDateTime(),
                'summary' => [
                    'status' => 'error',
                    'details' => [(string) $e->getMessage()],
                ],
            ]);
            Logger::error('Configuration import failed', [
                'mode' => $importMode,
                'sections' => $selectedSections,
                'error' => $e->getMessage(),
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
        $allowed = self::CONFIG_TRANSFER_ROOT_SECTIONS;

        return array_values(array_intersect($selected, $allowed));
    }

    private function buildConfigurationExportPayload(array $selectedSections, array $selectedFormIds, array $selectedStorageIds, bool $includeStorageContent = false): array
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $existingTables = array_map('strtolower', (array) $db->getTableList());
        $exportSections = [];

        foreach ($this->expandConfigSections($selectedSections) as $sectionKey) {
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

            $this->applyExportFilters($db, $query, $sectionKey, $columns, $selectedSections, $selectedFormIds, $selectedStorageIds);

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

        if ($includeStorageContent && in_array('storages', $selectedSections, true) && $selectedStorageIds !== []) {
            $exportSections['storage_content'] = $this->buildStorageContentExportSection($db, $existingTables, $selectedStorageIds);
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
                'include_storage_content' => $includeStorageContent ? 1 : 0,
            ],
            'data' => $exportSections,
        ];
    }

    private function buildStorageContentExportSection(DatabaseInterface $db, array $existingTables, array $selectedStorageIds): array
    {
        $query = $db->getQuery(true)
            ->select([
                $db->quoteName('id'),
                $db->quoteName('name'),
                $db->quoteName('title'),
                $db->quoteName('bytable'),
            ])
            ->from($db->quoteName('#__contentbuilderng_storages'))
            ->where($db->quoteName('id') . ' IN (' . implode(',', array_map('intval', $selectedStorageIds)) . ')')
            ->order($db->quoteName('title') . ' ASC, ' . $db->quoteName('name') . ' ASC, ' . $db->quoteName('id') . ' ASC');
        $db->setQuery($query);
        $storageRows = (array) $db->loadAssocList();

        $storages = [];
        $totalRows = 0;

        foreach ($storageRows as $storageRow) {
            $storageId = (int) ($storageRow['id'] ?? 0);
            $storageName = trim((string) ($storageRow['name'] ?? ''));
            $isBytable = (int) ($storageRow['bytable'] ?? 0) === 1;

            if ($storageId <= 0 || $storageName === '' || $isBytable) {
                continue;
            }

            $tableAlias = $this->resolveInternalStorageTableAlias($db, $existingTables, $storageName);
            if ($tableAlias === null) {
                continue;
            }

            $columns = array_keys((array) $db->getTableColumns($tableAlias, false));
            $contentQuery = $db->getQuery(true)
                ->select('*')
                ->from($db->quoteName($tableAlias));

            if (in_array('id', $columns, true)) {
                $contentQuery->order($db->quoteName('id') . ' ASC');
            }

            $db->setQuery($contentQuery);
            $rows = (array) $db->loadAssocList();
            $rowCount = count($rows);
            $totalRows += $rowCount;

            $storages[] = [
                'storage_id' => $storageId,
                'storage_name' => $storageName,
                'storage_title' => (string) ($storageRow['title'] ?? ''),
                'table' => $tableAlias,
                'row_count' => $rowCount,
                'rows' => $rows,
            ];
        }

        return [
            'type' => 'storage_content',
            'row_count' => $totalRows,
            'storages' => $storages,
        ];
    }

    private function resolveInternalStorageTableAlias(DatabaseInterface $db, array $existingTables, string $storageName): ?string
    {
        $storageName = trim($storageName);
        if ($storageName === '') {
            return null;
        }

        $prefixedAlias = '#__' . $storageName;
        $prefixedName = strtolower($db->replacePrefix($prefixedAlias));

        if (in_array($prefixedName, $existingTables, true)) {
            return $prefixedAlias;
        }

        $plainName = strtolower($storageName);
        if (in_array($plainName, $existingTables, true)) {
            return $storageName;
        }

        return null;
    }

    private function getSelectedConfigFormIds(): array
    {
        return $this->getSelectedNumericIds('cb_config_form_ids');
    }

    private function getSelectedConfigStorageIds(): array
    {
        return $this->getSelectedNumericIds('cb_config_storage_ids');
    }

    private function getSelectedConfigImportNames(string $inputKey): array
    {
        $selectedRaw = (array) Factory::getApplication()->input->get($inputKey, [], 'array');
        $selected = [];

        foreach ($selectedRaw as $selectedName) {
            $name = trim((string) $selectedName);
            if ($name !== '') {
                $selected[] = $name;
            }
        }

        return array_values(array_unique($selected));
    }

    private function expandConfigSections(array $selectedSections): array
    {
        $expanded = [];

        foreach ($selectedSections as $sectionKey) {
            if (!in_array($sectionKey, self::CONFIG_TRANSFER_ROOT_SECTIONS, true)) {
                continue;
            }

            $expanded[] = $sectionKey;

            if ($sectionKey === 'forms') {
                foreach (self::CONFIG_FORM_DEPENDENT_SECTIONS as $dependentSection) {
                    $expanded[] = $dependentSection;
                }
            }

            if ($sectionKey === 'storages') {
                foreach (self::CONFIG_STORAGE_DEPENDENT_SECTIONS as $dependentSection) {
                    $expanded[] = $dependentSection;
                }
            }
        }

        return array_values(array_unique($expanded));
    }

    private function applyExportFilters(
        DatabaseInterface $db,
        \Joomla\Database\QueryInterface $query,
        string $sectionKey,
        array $columns,
        array $selectedSections,
        array $selectedFormIds,
        array $selectedStorageIds
    ): void {
        if (
            in_array('forms', $selectedSections, true)
            && $selectedFormIds !== []
        ) {
            if ($sectionKey === 'forms' && in_array('id', $columns, true)) {
                $query->where($db->quoteName('id') . ' IN (' . implode(',', array_map('intval', $selectedFormIds)) . ')');
            }

            if (in_array($sectionKey, self::CONFIG_FORM_DEPENDENT_SECTIONS, true) && in_array('form_id', $columns, true)) {
                $query->where($db->quoteName('form_id') . ' IN (' . implode(',', array_map('intval', $selectedFormIds)) . ')');
            }
        }

        if (
            in_array('storages', $selectedSections, true)
            && $selectedStorageIds !== []
        ) {
            if ($sectionKey === 'storages' && in_array('id', $columns, true)) {
                $query->where($db->quoteName('id') . ' IN (' . implode(',', array_map('intval', $selectedStorageIds)) . ')');
            }

            if ($sectionKey === 'storage_fields' && in_array('storage_id', $columns, true)) {
                $query->where($db->quoteName('storage_id') . ' IN (' . implode(',', array_map('intval', $selectedStorageIds)) . ')');
            }
        }
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

    private function filterConfigurationImportPayload(
        array $payload,
        array $selectedSections,
        array $selectedFormNames,
        array $selectedStorageNames,
        array $selectedStorageContentNames = []
    ): array {
        $dataSections = is_array($payload['data'] ?? null) ? $payload['data'] : [];

        if (in_array('forms', $selectedSections, true) && $selectedFormNames !== []) {
            $formRows = is_array($dataSections['forms']['rows'] ?? null) ? $dataSections['forms']['rows'] : [];
            $selectedFormMap = array_fill_keys($selectedFormNames, true);
            $selectedFormIds = [];
            $filteredFormRows = [];

            foreach ($formRows as $row) {
                if (!is_array($row)) {
                    continue;
                }

                $formName = trim((string) ($row['name'] ?? ''));
                if ($formName === '' || !isset($selectedFormMap[$formName])) {
                    continue;
                }

                $filteredFormRows[] = $row;
                $rowId = (int) ($row['id'] ?? 0);
                if ($rowId > 0) {
                    $selectedFormIds[$rowId] = true;
                }
            }

            if (isset($dataSections['forms']) && is_array($dataSections['forms'])) {
                $dataSections['forms']['rows'] = array_values($filteredFormRows);
                $dataSections['forms']['row_count'] = count($filteredFormRows);
            }

            $sourceFormIds = array_keys($selectedFormIds);
            foreach (self::CONFIG_FORM_DEPENDENT_SECTIONS as $sectionKey) {
                $rows = is_array($dataSections[$sectionKey]['rows'] ?? null) ? $dataSections[$sectionKey]['rows'] : [];
                $filteredRows = [];

                foreach ($rows as $row) {
                    if (!is_array($row)) {
                        continue;
                    }

                    $formId = (int) ($row['form_id'] ?? 0);
                    if ($formId > 0 && isset($selectedFormIds[$formId])) {
                        $filteredRows[] = $row;
                    }
                }

                if (isset($dataSections[$sectionKey]) && is_array($dataSections[$sectionKey])) {
                    $dataSections[$sectionKey]['rows'] = array_values($filteredRows);
                    $dataSections[$sectionKey]['row_count'] = count($filteredRows);
                }
            }

            if (isset($payload['filters']) && is_array($payload['filters'])) {
                $payload['filters']['form_ids'] = array_map('intval', $sourceFormIds);
            }
        }

        if (in_array('storages', $selectedSections, true) && $selectedStorageNames !== []) {
            $storageRows = is_array($dataSections['storages']['rows'] ?? null) ? $dataSections['storages']['rows'] : [];
            $selectedStorageMap = array_fill_keys($selectedStorageNames, true);
            $selectedStorageIds = [];
            $filteredStorageRows = [];

            foreach ($storageRows as $row) {
                if (!is_array($row)) {
                    continue;
                }

                $storageName = trim((string) ($row['name'] ?? ''));
                if ($storageName === '' || !isset($selectedStorageMap[$storageName])) {
                    continue;
                }

                $filteredStorageRows[] = $row;
                $rowId = (int) ($row['id'] ?? 0);
                if ($rowId > 0) {
                    $selectedStorageIds[$rowId] = true;
                }
            }

            if (isset($dataSections['storages']) && is_array($dataSections['storages'])) {
                $dataSections['storages']['rows'] = array_values($filteredStorageRows);
                $dataSections['storages']['row_count'] = count($filteredStorageRows);
            }

            $storageFieldRows = is_array($dataSections['storage_fields']['rows'] ?? null) ? $dataSections['storage_fields']['rows'] : [];
            $filteredFieldRows = [];

            foreach ($storageFieldRows as $row) {
                if (!is_array($row)) {
                    continue;
                }

                $storageId = (int) ($row['storage_id'] ?? 0);
                if ($storageId > 0 && isset($selectedStorageIds[$storageId])) {
                    $filteredFieldRows[] = $row;
                }
            }

            if (isset($dataSections['storage_fields']) && is_array($dataSections['storage_fields'])) {
                $dataSections['storage_fields']['rows'] = array_values($filteredFieldRows);
                $dataSections['storage_fields']['row_count'] = count($filteredFieldRows);
            }

            if (isset($payload['filters']) && is_array($payload['filters'])) {
                $payload['filters']['storage_ids'] = array_map('intval', array_keys($selectedStorageIds));
            }

            $storageContentStorages = is_array($dataSections['storage_content']['storages'] ?? null)
                ? $dataSections['storage_content']['storages']
                : [];
            $filteredContentStorages = [];

            foreach ($storageContentStorages as $storageContentEntry) {
                if (!is_array($storageContentEntry)) {
                    continue;
                }

                $storageId = (int) ($storageContentEntry['storage_id'] ?? 0);
                if ($storageId > 0 && isset($selectedStorageIds[$storageId])) {
                    $filteredContentStorages[] = $storageContentEntry;
                }
            }

            if (isset($dataSections['storage_content']) && is_array($dataSections['storage_content'])) {
                if ($selectedStorageContentNames !== []) {
                    $selectedStorageContentMap = array_fill_keys($selectedStorageContentNames, true);
                    $filteredContentStorages = array_values(array_filter(
                        $filteredContentStorages,
                        static fn(array $entry): bool => isset($selectedStorageContentMap[(string) ($entry['storage_name'] ?? '')])
                    ));
                } else {
                    $filteredContentStorages = [];
                }

                $dataSections['storage_content']['storages'] = array_values($filteredContentStorages);
                $dataSections['storage_content']['row_count'] = array_sum(array_map(
                    static fn(array $entry): int => (int) ($entry['row_count'] ?? 0),
                    $filteredContentStorages
                ));
            }
        }

        $payload['data'] = $dataSections;

        return $payload;
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

        $includeStorageContent = (int) ($previous['include_storage_content'] ?? 0) === 1;
        if (array_key_exists('cb_export_storage_content', $postData)) {
            $includeStorageContent = (int) ($postData['cb_export_storage_content'] ?? 0) === 1;
        } elseif (((string) ($postData['task'] ?? '')) === 'about.exportConfiguration') {
            $includeStorageContent = false;
        }

        $app->setUserState(self::CONFIG_TRANSFER_SELECTION_STATE_KEY, [
            'touched' => 1,
            'sections' => $sections,
            'form_ids' => $formIds,
            'storage_ids' => $storageIds,
            'include_storage_content' => $includeStorageContent ? 1 : 0,
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

    private function shouldExportStorageContent(): bool
    {
        return Factory::getApplication()->input->getInt('cb_export_storage_content', 0) === 1;
    }

    private function resolveEffectiveExportSections(array $selectedSections, array $selectedFormIds, array $selectedStorageIds): array
    {
        $effective = [];

        foreach ($selectedSections as $sectionKey) {
            if ($sectionKey === 'forms' && $selectedFormIds !== []) {
                $effective[] = $sectionKey;
                continue;
            }

            if ($sectionKey === 'storages' && $selectedStorageIds !== []) {
                $effective[] = $sectionKey;
            }
        }

        return array_values(array_unique($effective));
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
                if ($sectionKey === 'component_params') {
                    $sectionPayload = $dataSections['component_params'] ?? null;
                    if (!is_array($sectionPayload)) {
                        $details[] = Text::sprintf('COM_CONTENTBUILDERNG_ABOUT_IMPORT_CONFIGURATION_DETAIL_SECTION_MISSING', 'component_params');
                        continue;
                    }

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

                if ($sectionKey === 'forms') {
                    $summary = $this->importFormsConfiguration($db, $dataSections, $importMode);
                    $tableRowsImported += (int) ($summary['rows'] ?? 0);
                    $tablesImported += (int) ($summary['tables'] ?? 0);
                    foreach ((array) ($summary['details'] ?? []) as $detail) {
                        $details[] = (string) $detail;
                    }
                    continue;
                }

                if ($sectionKey === 'storages') {
                    $summary = $this->importStoragesConfiguration($db, $dataSections, $importMode);
                    $tableRowsImported += (int) ($summary['rows'] ?? 0);
                    $tablesImported += (int) ($summary['tables'] ?? 0);
                    foreach ((array) ($summary['details'] ?? []) as $detail) {
                        $details[] = (string) $detail;
                    }
                }
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

    private function logConfigurationImportReport(array $summary, array $selectedSections, string $importMode): void
    {
        $details = array_values(array_filter(
            array_map('strval', (array) ($summary['details'] ?? [])),
            static fn(string $detail): bool => trim($detail) !== ''
        ));

        Logger::info('Configuration import completed', [
            'mode' => $importMode,
            'status' => (string) ($summary['status'] ?? 'ok'),
            'sections' => array_values($selectedSections),
            'tables' => (int) ($summary['tables'] ?? 0),
            'rows' => (int) ($summary['rows'] ?? 0),
            'details_count' => count($details),
        ]);

        foreach ($details as $detail) {
            Logger::info('Configuration import detail', [
                'mode' => $importMode,
                'detail' => $detail,
            ]);
        }
    }

    private function logConfigurationExportReport(array $payload, array $selectedSections, array $selectedFormIds, array $selectedStorageIds): void
    {
        $dataSections = is_array($payload['data'] ?? null) ? $payload['data'] : [];
        $details = [];
        $rows = 0;

        foreach ($dataSections as $sectionKey => $sectionPayload) {
            if (!is_array($sectionPayload)) {
                continue;
            }

            $type = (string) ($sectionPayload['type'] ?? '');
            if ($type === 'component_params') {
                $details[] = 'component_params: 1';
                continue;
            }

            $rowCount = (int) ($sectionPayload['row_count'] ?? 0);
            $rows += $rowCount;
            $details[] = (string) $sectionKey . ': ' . $rowCount;
        }

        Logger::info('Configuration export completed', [
            'sections' => array_values($selectedSections),
            'form_ids' => array_values($selectedFormIds),
            'storage_ids' => array_values($selectedStorageIds),
            'data_sections' => count($dataSections),
            'rows' => $rows,
            'details' => $details,
        ]);
    }

    private function buildConfigurationExportSummary(
        array $payload,
        array $selectedSections,
        array $selectedFormIds,
        array $selectedStorageIds,
        bool $includeStorageContent
    ): array {
        $dataSections = is_array($payload['data'] ?? null) ? $payload['data'] : [];
        $details = [];
        $rows = 0;

        foreach ($dataSections as $sectionKey => $sectionPayload) {
            if (!is_array($sectionPayload)) {
                continue;
            }

            $rowCount = (int) ($sectionPayload['row_count'] ?? 0);
            $rows += $rowCount;
            $details[] = (string) $sectionKey . ': ' . $rowCount;
        }

        return [
            'status' => 'ok',
            'tables' => count($dataSections),
            'rows' => $rows,
            'sections' => array_values($selectedSections),
            'form_ids' => array_values($selectedFormIds),
            'storage_ids' => array_values($selectedStorageIds),
            'include_storage_content' => $includeStorageContent ? 1 : 0,
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

    private function importFormsConfiguration(DatabaseInterface $db, array $dataSections, string $importMode): array
    {
        $details = [];
        $tables = 0;
        $rows = 0;

        $formsPayload = $dataSections['forms'] ?? null;
        if (!is_array($formsPayload)) {
            return [
                'tables' => 0,
                'rows' => 0,
                'details' => [Text::sprintf('COM_CONTENTBUILDERNG_ABOUT_IMPORT_CONFIGURATION_DETAIL_SECTION_MISSING', 'forms')],
            ];
        }

        $formRows = is_array($formsPayload['rows'] ?? null) ? $formsPayload['rows'] : [];
        [$formIdMap, $formsImported] = $this->importRowsByNaturalKey(
            $db,
            '#__contentbuilderng_forms',
            $formRows,
            ['name'],
            [],
            $importMode,
            true
        );
        $tables++;
        $rows += $formsImported;
        $details[] = Text::sprintf(
            'COM_CONTENTBUILDERNG_ABOUT_IMPORT_CONFIGURATION_DETAIL_TABLE_IMPORTED',
            '#__contentbuilderng_forms',
            $formsImported
        );

        if ($formIdMap === []) {
            return ['tables' => $tables, 'rows' => $rows, 'details' => $details];
        }

        $targetFormIds = array_values(array_unique(array_map('intval', array_values($formIdMap))));
        if ($importMode === self::CONFIG_IMPORT_MODE_REPLACE && $targetFormIds !== []) {
            $this->deleteRowsByIds($db, '#__contentbuilderng_elements', 'form_id', $targetFormIds);
            $this->deleteRowsByIds($db, '#__contentbuilderng_list_states', 'form_id', $targetFormIds);
            $this->deleteRowsByIds($db, '#__contentbuilderng_resource_access', 'form_id', $targetFormIds);
        }

        $elementsPayload = $dataSections['elements'] ?? null;
        if (is_array($elementsPayload)) {
            $elementRows = $this->remapRowsForeignKey(
                is_array($elementsPayload['rows'] ?? null) ? $elementsPayload['rows'] : [],
                'form_id',
                $formIdMap
            );
            [$elementIdMap, $elementsImported] = $this->importRowsByNaturalKey(
                $db,
                '#__contentbuilderng_elements',
                $elementRows,
                ['form_id', 'reference_id'],
                [],
                $importMode,
                true
            );
            $tables++;
            $rows += $elementsImported;
            $details[] = Text::sprintf(
                'COM_CONTENTBUILDERNG_ABOUT_IMPORT_CONFIGURATION_DETAIL_TABLE_IMPORTED',
                '#__contentbuilderng_elements',
                $elementsImported
            );
        } else {
            $elementIdMap = [];
            $details[] = Text::sprintf('COM_CONTENTBUILDERNG_ABOUT_IMPORT_CONFIGURATION_DETAIL_SECTION_MISSING', 'elements');
        }

        $listStatesPayload = $dataSections['list_states'] ?? null;
        if (is_array($listStatesPayload)) {
            $listStateRows = $this->remapRowsForeignKey(
                is_array($listStatesPayload['rows'] ?? null) ? $listStatesPayload['rows'] : [],
                'form_id',
                $formIdMap
            );
            [, $listStatesImported] = $this->importRowsByNaturalKey(
                $db,
                '#__contentbuilderng_list_states',
                $listStateRows,
                ['form_id', 'title'],
                [],
                $importMode,
                true
            );
            $tables++;
            $rows += $listStatesImported;
            $details[] = Text::sprintf(
                'COM_CONTENTBUILDERNG_ABOUT_IMPORT_CONFIGURATION_DETAIL_TABLE_IMPORTED',
                '#__contentbuilderng_list_states',
                $listStatesImported
            );
        } else {
            $details[] = Text::sprintf('COM_CONTENTBUILDERNG_ABOUT_IMPORT_CONFIGURATION_DETAIL_SECTION_MISSING', 'list_states');
        }

        $resourceAccessPayload = $dataSections['resource_access'] ?? null;
        if (is_array($resourceAccessPayload)) {
            $resourceRows = $this->remapRowsForeignKey(
                is_array($resourceAccessPayload['rows'] ?? null) ? $resourceAccessPayload['rows'] : [],
                'form_id',
                $formIdMap
            );
            $resourceRows = $this->remapRowsForeignKey($resourceRows, 'element_id', $elementIdMap);
            [, $resourceImported] = $this->importRowsByNaturalKey(
                $db,
                '#__contentbuilderng_resource_access',
                $resourceRows,
                ['type', 'element_id', 'resource_id'],
                ['form_id', 'hits'],
                $importMode,
                false
            );
            $tables++;
            $rows += $resourceImported;
            $details[] = Text::sprintf(
                'COM_CONTENTBUILDERNG_ABOUT_IMPORT_CONFIGURATION_DETAIL_TABLE_IMPORTED',
                '#__contentbuilderng_resource_access',
                $resourceImported
            );
        } else {
            $details[] = Text::sprintf('COM_CONTENTBUILDERNG_ABOUT_IMPORT_CONFIGURATION_DETAIL_SECTION_MISSING', 'resource_access');
        }

        return ['tables' => $tables, 'rows' => $rows, 'details' => $details];
    }

    private function importStoragesConfiguration(DatabaseInterface $db, array $dataSections, string $importMode): array
    {
        $details = [];
        $tables = 0;
        $rows = 0;

        $storagesPayload = $dataSections['storages'] ?? null;
        if (!is_array($storagesPayload)) {
            return [
                'tables' => 0,
                'rows' => 0,
                'details' => [Text::sprintf('COM_CONTENTBUILDERNG_ABOUT_IMPORT_CONFIGURATION_DETAIL_SECTION_MISSING', 'storages')],
            ];
        }

        $storageRows = is_array($storagesPayload['rows'] ?? null) ? $storagesPayload['rows'] : [];
        [$storageIdMap, $storagesImported] = $this->importRowsByNaturalKey(
            $db,
            '#__contentbuilderng_storages',
            $storageRows,
            ['name'],
            [],
            $importMode,
            true
        );
        $tables++;
        $rows += $storagesImported;
        $details[] = Text::sprintf(
            'COM_CONTENTBUILDERNG_ABOUT_IMPORT_CONFIGURATION_DETAIL_TABLE_IMPORTED',
            '#__contentbuilderng_storages',
            $storagesImported
        );

        if ($storageIdMap === []) {
            return ['tables' => $tables, 'rows' => $rows, 'details' => $details];
        }

        $targetStorageIds = array_values(array_unique(array_map('intval', array_values($storageIdMap))));
        if ($importMode === self::CONFIG_IMPORT_MODE_REPLACE && $targetStorageIds !== []) {
            $this->deleteRowsByIds($db, '#__contentbuilderng_storage_fields', 'storage_id', $targetStorageIds);
        }

        $storageFieldsPayload = $dataSections['storage_fields'] ?? null;
        if (is_array($storageFieldsPayload)) {
            $fieldRows = $this->remapRowsForeignKey(
                is_array($storageFieldsPayload['rows'] ?? null) ? $storageFieldsPayload['rows'] : [],
                'storage_id',
                $storageIdMap
            );
            [, $fieldsImported] = $this->importRowsByNaturalKey(
                $db,
                '#__contentbuilderng_storage_fields',
                $fieldRows,
                ['storage_id', 'name'],
                [],
                $importMode,
                true
            );
            $tables++;
            $rows += $fieldsImported;
            $details[] = Text::sprintf(
                'COM_CONTENTBUILDERNG_ABOUT_IMPORT_CONFIGURATION_DETAIL_TABLE_IMPORTED',
                '#__contentbuilderng_storage_fields',
                $fieldsImported
            );
        } else {
            $details[] = Text::sprintf('COM_CONTENTBUILDERNG_ABOUT_IMPORT_CONFIGURATION_DETAIL_SECTION_MISSING', 'storage_fields');
        }

        $storageContentPayload = $dataSections['storage_content'] ?? null;
        if (is_array($storageContentPayload)) {
            $contentImported = $this->importStorageContent($db, $storageContentPayload, $importMode);
            $rows += $contentImported;
            $details[] = Text::sprintf(
                'COM_CONTENTBUILDERNG_ABOUT_IMPORT_CONFIGURATION_DETAIL_TABLE_IMPORTED',
                'storage_content',
                $contentImported
            );
        }

        return ['tables' => $tables, 'rows' => $rows, 'details' => $details];
    }

    private function importStorageContent(DatabaseInterface $db, array $storageContentPayload, string $importMode): int
    {
        $entries = is_array($storageContentPayload['storages'] ?? null) ? $storageContentPayload['storages'] : [];
        $imported = 0;
        $existingTables = array_map('strtolower', (array) $db->getTableList());

        foreach ($entries as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $sourceStorageName = trim((string) ($entry['storage_name'] ?? ''));
            if ($sourceStorageName === '') {
                continue;
            }

            $query = $db->getQuery(true)
                ->select([
                    $db->quoteName('id'),
                    $db->quoteName('name'),
                    $db->quoteName('bytable'),
                ])
                ->from($db->quoteName('#__contentbuilderng_storages'))
                ->where($db->quoteName('name') . ' = ' . $db->quote($sourceStorageName));
            $db->setQuery($query, 0, 1);
            $storage = (array) $db->loadAssoc();

            $storageName = trim((string) ($storage['name'] ?? ''));
            $isBytable = (int) ($storage['bytable'] ?? 0) === 1;
            if ($storageName === '' || $isBytable) {
                continue;
            }

            $tableAlias = $this->resolveInternalStorageTableAlias($db, $existingTables, $storageName);
            if ($tableAlias === null) {
                continue;
            }

            $rows = is_array($entry['rows'] ?? null) ? $entry['rows'] : [];
            if ($rows === []) {
                continue;
            }

            $imported += $this->importConfigTableRows($db, $tableAlias, $rows, $importMode);
        }

        return $imported;
    }

    private function importRowsByNaturalKey(
        DatabaseInterface $db,
        string $tableAlias,
        array $rows,
        array $keyColumns,
        array $extraUpdateColumns,
        string $importMode,
        bool $trackSourceIds
    ): array {
        $columns = array_keys((array) $db->getTableColumns($tableAlias, false));
        if ($columns === []) {
            return [[], 0];
        }

        $trackedIds = [];
        $imported = 0;
        $hasIdColumn = in_array('id', $columns, true);

        foreach ($rows as $rowIndex => $row) {
            if (!is_array($row)) {
                continue;
            }

            $filtered = [];
            foreach ($columns as $columnName) {
                if (array_key_exists($columnName, $row)) {
                    $filtered[$columnName] = $row[$columnName];
                }
            }

            if ($filtered === []) {
                continue;
            }

            $keyValues = [];
            foreach ($keyColumns as $keyColumn) {
                $keyValue = $filtered[$keyColumn] ?? null;
                if ($keyValue === null || $keyValue === '') {
                    throw new \RuntimeException(
                        Text::sprintf(
                            'COM_CONTENTBUILDERNG_ABOUT_IMPORT_CONFIGURATION_ROW_ERROR',
                            $tableAlias,
                            ((int) $rowIndex) + 1,
                            'Missing natural key "' . $keyColumn . '"'
                        )
                    );
                }
                $keyValues[$keyColumn] = $keyValue;
            }

            try {
                $existingRow = $this->findRowByColumns($db, $tableAlias, $keyValues);
                $existingId = $this->findRowIdByColumns($db, $tableAlias, $keyValues);
                $sourceId = (int) ($filtered['id'] ?? 0);

                if ($existingId > 0) {
                    $updateData = $filtered;
                    unset($updateData['id']);
                    $updateData = $this->stripImportManagedAuditColumns($tableAlias, $updateData);

                    if ($importMode === self::CONFIG_IMPORT_MODE_MERGE && $extraUpdateColumns !== []) {
                        $allowedUpdateColumns = array_fill_keys(array_merge($keyColumns, $extraUpdateColumns), true);
                        $updateData = array_intersect_key($updateData, $allowedUpdateColumns);
                    }

                    if ($trackSourceIds && $sourceId > 0 && $hasIdColumn) {
                        $trackedIds[$sourceId] = $existingId;
                    }

                    if ($this->rowHasDifferences($existingRow, $updateData)) {
                        $updateData = $this->applyImportAuditColumns($tableAlias, $updateData, false);
                        if ($hasIdColumn) {
                            $this->updateRowById($db, $tableAlias, $existingId, $updateData);
                        } else {
                            $this->updateRowByColumns($db, $tableAlias, $keyValues, $updateData);
                        }
                        $imported++;
                    }

                    continue;
                }

                unset($filtered['id']);
                $filtered = $this->stripImportManagedAuditColumns($tableAlias, $filtered);
                $filtered = $this->applyImportAuditColumns($tableAlias, $filtered, true);
                $insertedId = $this->insertRow($db, $tableAlias, $filtered);
                if ($trackSourceIds && $sourceId > 0 && $insertedId > 0) {
                    $trackedIds[$sourceId] = $insertedId;
                }
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

        return [$trackedIds, $imported];
    }

    private function remapRowsForeignKey(array $rows, string $columnName, array $idMap): array
    {
        $remapped = [];

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            if (array_key_exists($columnName, $row)) {
                $sourceId = (int) $row[$columnName];
                if ($sourceId > 0 && isset($idMap[$sourceId])) {
                    $row[$columnName] = (int) $idMap[$sourceId];
                }
            }

            $remapped[] = $row;
        }

        return $remapped;
    }

    private function deleteRowsByIds(DatabaseInterface $db, string $tableAlias, string $columnName, array $ids): void
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn(int $id): bool => $id > 0)));
        if ($ids === []) {
            return;
        }

        $query = $db->getQuery(true)
            ->delete($db->quoteName($tableAlias))
            ->where($db->quoteName($columnName) . ' IN (' . implode(',', $ids) . ')');
        $db->setQuery($query)->execute();
    }

    private function findRowIdByColumns(DatabaseInterface $db, string $tableAlias, array $columnValues): int
    {
        $columns = array_keys((array) $db->getTableColumns($tableAlias, false));
        $query = $db->getQuery(true)
            ->select(in_array('id', $columns, true) ? $db->quoteName('id') : '1')
            ->from($db->quoteName($tableAlias));

        foreach ($columnValues as $columnName => $value) {
            $query->where(
                $db->quoteName($columnName) . ' = ' . ($value === null ? 'NULL' : $db->quote((string) $value))
            );
        }

        $db->setQuery($query, 0, 1);
        return (int) $db->loadResult();
    }

    private function findRowByColumns(DatabaseInterface $db, string $tableAlias, array $columnValues): array
    {
        $query = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName($tableAlias));

        foreach ($columnValues as $columnName => $value) {
            $query->where(
                $db->quoteName($columnName) . ' = ' . ($value === null ? 'NULL' : $db->quote((string) $value))
            );
        }

        $db->setQuery($query, 0, 1);
        $row = $db->loadAssoc();

        return is_array($row) ? $row : [];
    }

    private function rowHasDifferences(array $existingRow, array $updateData): bool
    {
        foreach ($updateData as $columnName => $value) {
            $existingValue = $existingRow[$columnName] ?? null;
            $normalizedExisting = $existingValue === null ? null : (string) $existingValue;
            $normalizedIncoming = $value === null ? null : (string) $value;

            if ($normalizedExisting !== $normalizedIncoming) {
                return true;
            }
        }

        return false;
    }

    private function stripImportManagedAuditColumns(string $tableAlias, array $row): array
    {
        foreach ($this->getImportManagedAuditColumns($tableAlias) as $columnName) {
            unset($row[$columnName]);
        }

        return $row;
    }

    private function applyImportAuditColumns(string $tableAlias, array $row, bool $isNew): array
    {
        $now = Factory::getDate()->toSql();
        $user = Factory::getApplication()->getIdentity();

        if ($tableAlias === '#__contentbuilderng_forms') {
            if ($isNew) {
                if (empty($row['created']) || str_starts_with((string) $row['created'], '0000-00-00')) {
                    $row['created'] = $now;
                }
                if (empty($row['created_by'])) {
                    $row['created_by'] = (int) ($user->id ?? 0);
                }
            }

            $row['modified'] = $now;
            $row['modified_by'] = (int) ($user->id ?? 0);
            $row['last_update'] = $now;

            return $row;
        }

        if ($tableAlias === '#__contentbuilderng_storages') {
            $actor = trim((string) (($user->username ?? '') !== '' ? $user->username : ($user->name ?? '')));
            if ($actor === '') {
                $actor = 'system';
            }

            if ($isNew) {
                if (empty($row['created']) || str_starts_with((string) $row['created'], '0000-00-00')) {
                    $row['created'] = $now;
                }
                if (trim((string) ($row['created_by'] ?? '')) === '') {
                    $row['created_by'] = $actor;
                }
            }

            $row['modified'] = $now;
            $row['modified_by'] = $actor;
        }

        return $row;
    }

    private function getImportManagedAuditColumns(string $tableAlias): array
    {
        if ($tableAlias === '#__contentbuilderng_forms') {
            return ['modified', 'modified_by', 'last_update'];
        }

        if ($tableAlias === '#__contentbuilderng_storages') {
            return ['modified', 'modified_by'];
        }

        return [];
    }

    private function updateRowById(DatabaseInterface $db, string $tableAlias, int $id, array $row): void
    {
        if ($id <= 0 || $row === []) {
            return;
        }

        $query = $db->getQuery(true)
            ->update($db->quoteName($tableAlias));

        $setCount = 0;
        foreach ($row as $columnName => $value) {
            $query->set(
                $db->quoteName($columnName) . ' = ' . ($value === null ? 'NULL' : $db->quote((string) $value))
            );
            $setCount++;
        }

        if ($setCount === 0) {
            return;
        }

        $query->where($db->quoteName('id') . ' = ' . $id);
        $db->setQuery($query)->execute();
    }

    private function updateRowByColumns(DatabaseInterface $db, string $tableAlias, array $columnValues, array $row): void
    {
        if ($columnValues === [] || $row === []) {
            return;
        }

        $query = $db->getQuery(true)
            ->update($db->quoteName($tableAlias));

        $setCount = 0;
        foreach ($row as $columnName => $value) {
            $query->set(
                $db->quoteName($columnName) . ' = ' . ($value === null ? 'NULL' : $db->quote((string) $value))
            );
            $setCount++;
        }

        if ($setCount === 0) {
            return;
        }

        foreach ($columnValues as $columnName => $value) {
            $query->where(
                $db->quoteName($columnName) . ' = ' . ($value === null ? 'NULL' : $db->quote((string) $value))
            );
        }

        $db->setQuery($query)->execute();
    }

    private function insertRow(DatabaseInterface $db, string $tableAlias, array $row): int
    {
        if ($row === []) {
            return 0;
        }

        $query = $db->getQuery(true)
            ->insert($db->quoteName($tableAlias))
            ->columns(array_map([$db, 'quoteName'], array_keys($row)));

        $values = [];
        foreach ($row as $value) {
            $values[] = $value === null ? 'NULL' : $db->quote((string) $value);
        }

        $query->values(implode(',', $values));
        $db->setQuery($query)->execute();

        return (int) $db->insertid();
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
            'loaded_at' => $this->getJoomlaLocalDateTime(),
            'truncated' => $truncated ? 1 : 0,
            'tail_bytes' => $tailBytes,
        ];
    }

    private function getJoomlaLocalDateTime(): string
    {
        $app = Factory::getApplication();
        $offset = is_object($app) && method_exists($app, 'get') ? (string) $app->get('offset', 'UTC') : 'UTC';

        try {
            $timezone = new \DateTimeZone($offset !== '' ? $offset : 'UTC');
        } catch (\Throwable) {
            $timezone = new \DateTimeZone('UTC');
        }

        return (new \DateTimeImmutable('now', $timezone))->format('Y-m-d H:i:s');
    }

    private function getAuthorizedApplication(): AdministratorApplication
    {
        /** @var AdministratorApplication $app */
        $app = Factory::getApplication();
        $user = $app->getIdentity();

        if (!$user->authorise('core.manage', 'com_contentbuilderng')) {
            throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        return $app;
    }

    private function createRepairWorkflowState(): array
    {
        $steps = [];

        foreach (self::REPAIR_WORKFLOW_STEPS as $stepId) {
            $steps[] = [
                'id' => $stepId,
                'status' => 'pending',
                'decision' => '',
                'completed_at' => '',
                'result' => [
                    'level' => 'message',
                    'summary' => '',
                    'lines' => [],
                ],
            ];
        }

        $now = $this->getJoomlaLocalDateTime();

        return [
            'active' => true,
            'completed' => false,
            'current_step' => 0,
            'steps' => $steps,
            'started_at' => $now,
            'updated_at' => $now,
        ];
    }

    private function getRepairWorkflowState(AdministratorApplication $app): array
    {
        $workflow = $app->getUserState(self::REPAIR_WORKFLOW_STATE_KEY, []);

        return is_array($workflow) ? $workflow : [];
    }

    private function runRepairWorkflowStep(string $stepId): array
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);

        return match ($stepId) {
            'table_encoding' => $this->buildTableEncodingStepResult(PackedDataMigrationHelper::repairTableCollationsStep($db)),
            'packed_data' => $this->buildPackedDataStepResult(PackedDataMigrationHelper::migratePackedPayloadsStep($db)),
            'audit_columns' => $this->buildAuditColumnsStepResult(\CB\Component\Contentbuilderng\Administrator\Helper\StorageAuditColumnsHelper::repair($db)),
            'plugin_duplicates' => $this->buildPluginDuplicateStepResult(\CB\Component\Contentbuilderng\Administrator\Helper\PluginExtensionDedupHelper::repair($db)),
            'historical_menu_entries' => $this->buildHistoricalMenuStepResult(PackedDataMigrationHelper::repairLegacyMenuEntriesStep($db)),
            default => throw new \RuntimeException('Unknown repair step: ' . $stepId),
        };
    }

    private function buildTableEncodingStepResult(array $summary): array
    {
        $supported = (bool) ($summary['supported'] ?? false);
        $target = (string) ($summary['target_collation'] ?? 'utf8mb4_0900_ai_ci');
        $scanned = (int) ($summary['scanned'] ?? 0);
        $converted = (int) ($summary['converted'] ?? 0);
        $unchanged = (int) ($summary['unchanged'] ?? 0);
        $errors = (int) ($summary['errors'] ?? 0);
        $lines = [];

        if (!$supported) {
            $lines[] = Text::sprintf('COM_CONTENTBUILDERNG_COLLATION_REPAIR_UNSUPPORTED', $target);

            return [
                'level' => 'warning',
                'summary' => Text::sprintf('COM_CONTENTBUILDERNG_DB_REPAIR_WORKFLOW_ENCODING_SUMMARY', $scanned, $converted, $unchanged, $errors),
                'lines' => $lines,
            ];
        }

        foreach ((array) ($summary['tables'] ?? []) as $table) {
            if (!is_array($table)) {
                continue;
            }

            $from = trim((string) ($table['from'] ?? ''));
            if ($from === '') {
                $from = Text::_('COM_CONTENTBUILDERNG_NOT_AVAILABLE');
            }

            $status = (string) ($table['status'] ?? '');
            if ($status === 'converted') {
                $lines[] = Text::sprintf(
                    'COM_CONTENTBUILDERNG_COLLATION_REPAIR_TABLE_CONVERTED',
                    (string) ($table['table'] ?? ''),
                    $from,
                    (string) ($table['to'] ?? $target)
                );
            } elseif ($status === 'error') {
                $lines[] = Text::sprintf(
                    'COM_CONTENTBUILDERNG_COLLATION_REPAIR_TABLE_ERROR',
                    (string) ($table['table'] ?? ''),
                    $from,
                    (string) ($table['to'] ?? $target),
                    (string) ($table['error'] ?? '')
                );
            }
        }

        foreach ((array) ($summary['warnings'] ?? []) as $warning) {
            $warning = trim((string) $warning);
            if ($warning !== '') {
                $lines[] = Text::sprintf('COM_CONTENTBUILDERNG_COLLATION_REPAIR_WARNING', $warning);
            }
        }

        return [
            'level' => $errors > 0 ? 'warning' : 'message',
            'summary' => Text::sprintf('COM_CONTENTBUILDERNG_DB_REPAIR_WORKFLOW_ENCODING_SUMMARY', $scanned, $converted, $unchanged, $errors),
            'lines' => $lines,
        ];
    }

    private function buildPackedDataStepResult(array $summary): array
    {
        $lines = [];

        foreach ((array) ($summary['tables'] ?? []) as $table) {
            if (!is_array($table)) {
                continue;
            }

            $lines[] = Text::sprintf(
                'COM_CONTENTBUILDERNG_PACKED_MIGRATION_TABLE_SUMMARY',
                (string) ($table['table'] ?? ''),
                (string) ($table['column'] ?? ''),
                (int) ($table['scanned'] ?? 0),
                (int) ($table['candidates'] ?? 0),
                (int) ($table['migrated'] ?? 0),
                (int) ($table['unchanged'] ?? 0),
                (int) ($table['errors'] ?? 0)
            );
        }

        return [
            'level' => (int) ($summary['errors'] ?? 0) > 0 ? 'warning' : 'message',
            'summary' => Text::sprintf(
                'COM_CONTENTBUILDERNG_DB_REPAIR_WORKFLOW_PACKED_DATA_SUMMARY',
                (int) ($summary['scanned'] ?? 0),
                (int) ($summary['candidates'] ?? 0),
                (int) ($summary['migrated'] ?? 0),
                (int) ($summary['unchanged'] ?? 0),
                (int) ($summary['errors'] ?? 0)
            ),
            'lines' => $lines,
        ];
    }

    private function buildAuditColumnsStepResult(array $summary): array
    {
        $lines = [];

        foreach ((array) ($summary['tables'] ?? []) as $table) {
            if (!is_array($table)) {
                continue;
            }

            $status = (string) ($table['status'] ?? '');
            $missing = (array) ($table['missing'] ?? []);
            $added = (array) ($table['added'] ?? []);
            $missingLabel = $missing !== [] ? implode(', ', $missing) : Text::_('COM_CONTENTBUILDERNG_NOT_AVAILABLE');
            $addedLabel = $added !== [] ? implode(', ', $added) : Text::_('COM_CONTENTBUILDERNG_NOT_AVAILABLE');

            if ($status === 'repaired') {
                $lines[] = Text::sprintf(
                    'COM_CONTENTBUILDERNG_AUDIT_COLUMNS_REPAIR_TABLE_REPAIRED',
                    (string) ($table['table'] ?? ''),
                    $missingLabel,
                    $addedLabel
                );
            } elseif ($status === 'partial') {
                $lines[] = Text::sprintf(
                    'COM_CONTENTBUILDERNG_AUDIT_COLUMNS_REPAIR_TABLE_PARTIAL',
                    (string) ($table['table'] ?? ''),
                    $missingLabel,
                    $addedLabel,
                    (string) ($table['error'] ?? '')
                );
            } elseif ($status === 'error') {
                $lines[] = Text::sprintf(
                    'COM_CONTENTBUILDERNG_AUDIT_COLUMNS_REPAIR_TABLE_ERROR',
                    (string) ($table['table'] ?? ''),
                    $missingLabel,
                    (string) ($table['error'] ?? '')
                );
            }
        }

        foreach ((array) ($summary['warnings'] ?? []) as $warning) {
            $warning = trim((string) $warning);
            if ($warning !== '') {
                $lines[] = Text::sprintf('COM_CONTENTBUILDERNG_AUDIT_COLUMNS_REPAIR_WARNING', $warning);
            }
        }

        return [
            'level' => (int) ($summary['errors'] ?? 0) > 0 ? 'warning' : 'message',
            'summary' => Text::sprintf(
                'COM_CONTENTBUILDERNG_AUDIT_COLUMNS_REPAIR_SUMMARY',
                (int) ($summary['scanned'] ?? 0),
                (int) ($summary['issues'] ?? 0),
                (int) ($summary['repaired'] ?? 0),
                (int) ($summary['unchanged'] ?? 0),
                (int) ($summary['errors'] ?? 0)
            ),
            'lines' => $lines,
        ];
    }

    private function buildPluginDuplicateStepResult(array $summary): array
    {
        $lines = [];

        foreach ((array) ($summary['groups'] ?? []) as $group) {
            if (!is_array($group)) {
                continue;
            }

            $canonicalFolder = trim((string) ($group['canonical_folder'] ?? ''));
            $canonicalElement = trim((string) ($group['canonical_element'] ?? ''));
            $canonicalLabel = $canonicalFolder !== '' || $canonicalElement !== ''
                ? $canonicalFolder . '/' . $canonicalElement
                : Text::_('COM_CONTENTBUILDERNG_NOT_AVAILABLE');
            $removedIds = array_values(array_map(static fn($id): int => (int) $id, (array) ($group['removed_ids'] ?? [])));
            $removedLabel = $removedIds !== [] ? implode(', ', $removedIds) : Text::_('COM_CONTENTBUILDERNG_NOT_AVAILABLE');
            $status = (string) ($group['status'] ?? '');

            if ($status === 'repaired') {
                $lines[] = Text::sprintf(
                    'COM_CONTENTBUILDERNG_PLUGIN_DUPLICATES_REPAIR_GROUP_REPAIRED',
                    $canonicalLabel,
                    (int) ($group['keep_id'] ?? 0),
                    $removedLabel
                );
            } elseif ($status === 'error') {
                $lines[] = Text::sprintf(
                    'COM_CONTENTBUILDERNG_PLUGIN_DUPLICATES_REPAIR_GROUP_ERROR',
                    $canonicalLabel,
                    (int) ($group['keep_id'] ?? 0),
                    $removedLabel,
                    (string) ($group['error'] ?? '')
                );
            }
        }

        foreach ((array) ($summary['warnings'] ?? []) as $warning) {
            $warning = trim((string) $warning);
            if ($warning !== '') {
                $lines[] = Text::sprintf('COM_CONTENTBUILDERNG_PLUGIN_DUPLICATES_REPAIR_WARNING', $warning);
            }
        }

        return [
            'level' => (int) ($summary['errors'] ?? 0) > 0 ? 'warning' : 'message',
            'summary' => Text::sprintf(
                'COM_CONTENTBUILDERNG_PLUGIN_DUPLICATES_REPAIR_SUMMARY',
                (int) ($summary['scanned'] ?? 0),
                (int) ($summary['issues'] ?? 0),
                (int) ($summary['repaired'] ?? 0),
                (int) ($summary['unchanged'] ?? 0),
                (int) ($summary['rows_removed'] ?? 0),
                (int) ($summary['errors'] ?? 0)
            ),
            'lines' => $lines,
        ];
    }

    private function buildHistoricalMenuStepResult(array $summary): array
    {
        $lines = [];

        foreach ((array) ($summary['entries'] ?? []) as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $oldTitle = trim((string) ($entry['old_title'] ?? ''));
            $newTitle = trim((string) ($entry['new_title'] ?? ''));
            $oldTitle = $oldTitle !== '' ? $oldTitle : Text::_('COM_CONTENTBUILDERNG_NOT_AVAILABLE');
            $newTitle = $newTitle !== '' ? $newTitle : Text::_('COM_CONTENTBUILDERNG_NOT_AVAILABLE');
            $status = (string) ($entry['status'] ?? '');

            if ($status === 'repaired') {
                $lines[] = Text::sprintf(
                    'COM_CONTENTBUILDERNG_HISTORICAL_MENU_REPAIR_ENTRY_REPAIRED',
                    (int) ($entry['menu_id'] ?? 0),
                    $oldTitle,
                    $newTitle
                );
            } elseif ($status === 'error') {
                $lines[] = Text::sprintf(
                    'COM_CONTENTBUILDERNG_HISTORICAL_MENU_REPAIR_ENTRY_ERROR',
                    (int) ($entry['menu_id'] ?? 0),
                    $oldTitle,
                    $newTitle,
                    (string) ($entry['error'] ?? '')
                );
            }
        }

        foreach ((array) ($summary['warnings'] ?? []) as $warning) {
            $warning = trim((string) $warning);
            if ($warning !== '') {
                $lines[] = Text::sprintf('COM_CONTENTBUILDERNG_HISTORICAL_MENU_REPAIR_WARNING', $warning);
            }
        }

        return [
            'level' => (int) ($summary['errors'] ?? 0) > 0 ? 'warning' : 'message',
            'summary' => Text::sprintf(
                'COM_CONTENTBUILDERNG_HISTORICAL_MENU_REPAIR_SUMMARY',
                (int) ($summary['scanned'] ?? 0),
                (int) ($summary['issues'] ?? 0),
                (int) ($summary['repaired'] ?? 0),
                (int) ($summary['unchanged'] ?? 0),
                (int) ($summary['errors'] ?? 0)
            ),
            'lines' => $lines,
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
