<?php
/**
 * @package     ContentBuilder NG
 * @author      XDA+GIL
 * @link        https://breezingforms-ng.vcmb.fr
 * @copyright   Copyright © 2026 by XDA+GIL
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

\defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

$mode = in_array((string) ($this->mode ?? 'export'), ['export', 'import'], true) ? (string) $this->mode : 'export';
$isExportMode = $mode === 'export';
$isImportMode = $mode === 'import';
$importReport = is_array($this->importReport ?? null) ? $this->importReport : [];
$importSummary = is_array($importReport['summary'] ?? null) ? $importReport['summary'] : [];
$importDetails = array_values(array_filter(array_map('strval', (array) ($importSummary['details'] ?? [])), static fn(string $v): bool => trim($v) !== ''));
$importGeneratedAt = (string) ($importReport['generated_at'] ?? Text::_('COM_CONTENTBUILDERNG_NOT_AVAILABLE'));
$importTablesCount = (int) ($importSummary['tables'] ?? 0);
$importRowsCount = (int) ($importSummary['rows'] ?? 0);
$selectedSections = array_fill_keys((array) ($this->selectedSections ?? []), true);
$selectedFormIds = array_fill_keys(array_map('intval', (array) ($this->selectedFormIds ?? [])), true);
$selectedStorageIds = array_fill_keys(array_map('intval', (array) ($this->selectedStorageIds ?? [])), true);
?>

<form
    action="<?php echo Route::_('index.php?option=com_contentbuilderng&view=configtransfer&mode=' . ($isExportMode ? 'export' : 'import')); ?>"
    method="post"
    name="adminForm"
    id="adminForm"
    enctype="multipart/form-data"
>
    <div class="container-fluid">
        <div class="row g-3">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <h2 class="h5 mb-2"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_CONFIG_TRANSFER_TITLE'); ?></h2>
                        <p class="text-muted mb-3"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_CONFIG_TRANSFER_DESC'); ?></p>

                        <ul class="nav nav-tabs mb-3" role="tablist" aria-label="<?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_CONFIG_TRANSFER_TITLE'); ?>">
                            <li class="nav-item" role="presentation">
                                <a
                                    class="nav-link <?php echo $isExportMode ? 'active' : ''; ?>"
                                    href="<?php echo Route::_('index.php?option=com_contentbuilderng&view=configtransfer&mode=export'); ?>"
                                    role="tab"
                                    aria-selected="<?php echo $isExportMode ? 'true' : 'false'; ?>"
                                >
                                    <span class="fa fa-download me-1" aria-hidden="true"></span>
                                    <?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_EXPORT_CONFIGURATION'); ?>
                                </a>
                            </li>
                            <li class="nav-item" role="presentation">
                                <a
                                    class="nav-link <?php echo $isImportMode ? 'active' : ''; ?>"
                                    href="<?php echo Route::_('index.php?option=com_contentbuilderng&view=configtransfer&mode=import'); ?>"
                                    role="tab"
                                    aria-selected="<?php echo $isImportMode ? 'true' : 'false'; ?>"
                                >
                                    <span class="fa fa-upload me-1" aria-hidden="true"></span>
                                    <?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_IMPORT_CONFIGURATION'); ?>
                                </a>
                            </li>
                        </ul>

                        <div class="row g-3">
                            <div class="col-lg-5">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <label class="form-label fw-semibold mb-0"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_CONFIG_SECTIONS_LABEL'); ?></label>
                                    <span class="d-inline-flex gap-1">
                                        <button type="button" class="btn btn-sm btn-outline-secondary" id="cb-config-sections-check-all">
                                            <?php echo Text::_('JGLOBAL_SELECTION_ALL'); ?>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-secondary" id="cb-config-sections-uncheck-all">
                                            <?php echo Text::_('JGLOBAL_SELECTION_NONE'); ?>
                                        </button>
                                    </span>
                                </div>
                                <div class="border rounded p-3" style="max-height: 340px; overflow-y: auto;">
                                    <?php foreach ((array) ($this->configSections ?? []) as $sectionKey => $sectionMeta) : ?>
                                        <?php
                                        $sectionLabel = trim((string) ($sectionMeta['label'] ?? ''));
                                        $sectionDescription = trim((string) ($sectionMeta['description'] ?? ''));
                                        ?>
                                        <div class="form-check mb-2">
                                            <input
                                                class="form-check-input cb-config-section-toggle"
                                                type="checkbox"
                                                name="cb_config_sections[]"
                                                id="cb_config_section_<?php echo htmlspecialchars((string) $sectionKey, ENT_QUOTES, 'UTF-8'); ?>"
                                                value="<?php echo htmlspecialchars((string) $sectionKey, ENT_QUOTES, 'UTF-8'); ?>"
                                                <?php echo isset($selectedSections[(string) $sectionKey]) ? 'checked="checked"' : ''; ?>
                                            >
                                            <label class="form-check-label" for="cb_config_section_<?php echo htmlspecialchars((string) $sectionKey, ENT_QUOTES, 'UTF-8'); ?>">
                                                <?php echo htmlspecialchars($sectionLabel, ENT_QUOTES, 'UTF-8'); ?>
                                            </label>
                                            <?php if ($sectionDescription !== '') : ?>
                                                <small class="d-block text-muted"><?php echo htmlspecialchars($sectionDescription, ENT_QUOTES, 'UTF-8'); ?></small>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <div class="col-lg-7">
                                <?php if ($isExportMode) : ?>
                                    <div class="mb-3" id="cb-config-forms-box">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <label class="form-label fw-semibold mb-0"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_CONFIG_SELECT_FORMS'); ?></label>
                                            <span class="d-inline-flex gap-1">
                                                <button type="button" class="btn btn-sm btn-outline-secondary" id="cb-config-forms-check-all">
                                                    <?php echo Text::_('JGLOBAL_SELECTION_ALL'); ?>
                                                </button>
                                                <button type="button" class="btn btn-sm btn-outline-secondary" id="cb-config-forms-uncheck-all">
                                                    <?php echo Text::_('JGLOBAL_SELECTION_NONE'); ?>
                                                </button>
                                            </span>
                                        </div>
                                        <div class="border rounded p-3" style="max-height: 220px; overflow-y: auto;">
                                            <?php if (empty($this->forms)) : ?>
                                                <div class="alert alert-info mb-0"><?php echo Text::_('COM_CONTENTBUILDERNG_NOT_AVAILABLE'); ?></div>
                                            <?php else : ?>
                                                <?php foreach ((array) $this->forms as $formRow) : ?>
                                                    <?php
                                                    $formId = (int) ($formRow['id'] ?? 0);
                                                    $formName = trim((string) ($formRow['name'] ?? ''));
                                                    if ($formName === '') {
                                                        $formName = '#' . $formId;
                                                    }
                                                    $formType = trim((string) ($formRow['type'] ?? ''));
                                                    $formReferenceId = (string) ($formRow['reference_id'] ?? '');
                                                    $isPublished = (int) ($formRow['published'] ?? 0) === 1;
                                                    $meta = [];
                                                    if ($formType !== '') {
                                                        $meta[] = $formType;
                                                    }
                                                    if ($formReferenceId !== '') {
                                                        $meta[] = '#' . $formReferenceId;
                                                    }
                                                    $meta[] = $isPublished ? Text::_('JPUBLISHED') : Text::_('JUNPUBLISHED');
                                                    ?>
                                                    <div class="form-check mb-1">
                                                        <input
                                                            class="form-check-input cb-config-form-item"
                                                            type="checkbox"
                                                            name="cb_config_form_ids[]"
                                                            id="cb_config_form_<?php echo $formId; ?>"
                                                            value="<?php echo $formId; ?>"
                                                            <?php echo isset($selectedFormIds[$formId]) ? 'checked="checked"' : ''; ?>
                                                        >
                                                        <label class="form-check-label" for="cb_config_form_<?php echo $formId; ?>">
                                                            <?php echo htmlspecialchars($formName, ENT_QUOTES, 'UTF-8'); ?>
                                                            <small class="text-muted">(<?php echo htmlspecialchars(implode(' / ', $meta), ENT_QUOTES, 'UTF-8'); ?>)</small>
                                                        </label>
                                                    </div>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <div class="mb-2" id="cb-config-storages-box">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <label class="form-label fw-semibold mb-0"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_CONFIG_SELECT_STORAGES'); ?></label>
                                            <span class="d-inline-flex gap-1">
                                                <button type="button" class="btn btn-sm btn-outline-secondary" id="cb-config-storages-check-all">
                                                    <?php echo Text::_('JGLOBAL_SELECTION_ALL'); ?>
                                                </button>
                                                <button type="button" class="btn btn-sm btn-outline-secondary" id="cb-config-storages-uncheck-all">
                                                    <?php echo Text::_('JGLOBAL_SELECTION_NONE'); ?>
                                                </button>
                                            </span>
                                        </div>
                                        <div class="border rounded p-3" style="max-height: 220px; overflow-y: auto;">
                                            <?php if (empty($this->storages)) : ?>
                                                <div class="alert alert-info mb-0"><?php echo Text::_('COM_CONTENTBUILDERNG_NOT_AVAILABLE'); ?></div>
                                            <?php else : ?>
                                                <?php foreach ((array) $this->storages as $storageRow) : ?>
                                                    <?php
                                                    $storageId = (int) ($storageRow['id'] ?? 0);
                                                    $storageTitle = trim((string) ($storageRow['title'] ?? ''));
                                                    $storageName = trim((string) ($storageRow['name'] ?? ''));
                                                    $storageLabel = $storageTitle !== '' ? $storageTitle : ($storageName !== '' ? $storageName : ('#' . $storageId));
                                                    $storageMeta = $storageName !== '' ? $storageName : ('#' . $storageId);
                                                    if ((int) ($storageRow['bytable'] ?? 0) === 1) {
                                                        $storageMeta .= ' / bytable';
                                                    }
                                                    ?>
                                                    <div class="form-check mb-1">
                                                        <input
                                                            class="form-check-input cb-config-storage-item"
                                                            type="checkbox"
                                                            name="cb_config_storage_ids[]"
                                                            id="cb_config_storage_<?php echo $storageId; ?>"
                                                            value="<?php echo $storageId; ?>"
                                                            <?php echo isset($selectedStorageIds[$storageId]) ? 'checked="checked"' : ''; ?>
                                                        >
                                                        <label class="form-check-label" for="cb_config_storage_<?php echo $storageId; ?>">
                                                            <?php echo htmlspecialchars($storageLabel, ENT_QUOTES, 'UTF-8'); ?>
                                                            <small class="text-muted">(<?php echo htmlspecialchars($storageMeta, ENT_QUOTES, 'UTF-8'); ?>)</small>
                                                        </label>
                                                    </div>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <?php if ($isImportMode) : ?>
                                    <div class="mb-3">
                                        <label class="form-label fw-semibold"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_IMPORT_CONFIGURATION_MODE_LABEL'); ?></label>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="cb_config_import_mode" id="cb_config_import_mode_merge" value="merge" checked="checked">
                                            <label class="form-check-label" for="cb_config_import_mode_merge">
                                                <?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_IMPORT_CONFIGURATION_MODE_MERGE'); ?>
                                            </label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="cb_config_import_mode" id="cb_config_import_mode_replace" value="replace">
                                            <label class="form-check-label" for="cb_config_import_mode_replace">
                                                <?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_IMPORT_CONFIGURATION_MODE_REPLACE'); ?>
                                            </label>
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <label for="cb_config_import_file" class="form-label fw-semibold"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_IMPORT_CONFIGURATION_FILE_LABEL'); ?></label>
                                        <input
                                            type="file"
                                            class="form-control"
                                            id="cb_config_import_file"
                                            name="cb_config_import_file"
                                            accept=".json,application/json"
                                        >
                                        <small class="text-muted d-block mt-1"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_IMPORT_CONFIGURATION_FILE_HELP'); ?></small>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="d-flex justify-content-end gap-2 mt-3">
                            <button type="submit" class="btn <?php echo $isExportMode ? 'btn-success cb-config-export-btn' : 'btn-primary'; ?>">
                                <span class="fa <?php echo $isExportMode ? 'fa-download' : 'fa-upload'; ?> me-1" aria-hidden="true"></span>
                                <?php echo $isExportMode ? Text::_('COM_CONTENTBUILDERNG_ABOUT_EXPORT_CONFIGURATION') : Text::_('COM_CONTENTBUILDERNG_ABOUT_IMPORT_CONFIGURATION'); ?>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <?php if ($isImportMode) : ?>
                <div class="col-12">
                    <div class="card">
                        <div class="card-body">
                            <h3 class="h6 card-title mb-2"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_IMPORT_LOG_TITLE'); ?></h3>
                            <?php if ($importReport === []) : ?>
                                <div class="alert alert-info mb-0"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_IMPORT_LOG_EMPTY'); ?></div>
                            <?php else : ?>
                                <p class="text-muted small mb-2">
                                    <?php echo Text::sprintf('COM_CONTENTBUILDERNG_ABOUT_IMPORT_LOG_LAST_RUN', $importGeneratedAt, $importTablesCount, $importRowsCount); ?>
                                </p>
                                <?php if ($importDetails === []) : ?>
                                    <div class="alert alert-secondary mb-0"><?php echo Text::_('COM_CONTENTBUILDERNG_NOT_AVAILABLE'); ?></div>
                                <?php else : ?>
                                    <ul class="list-group list-group-flush">
                                        <?php foreach ($importDetails as $importDetail) : ?>
                                            <li class="list-group-item px-2 py-1"><?php echo htmlspecialchars($importDetail, ENT_QUOTES, 'UTF-8'); ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <input type="hidden" name="task" id="cb_config_task" value="<?php echo $isExportMode ? 'about.exportConfiguration' : 'about.importConfiguration'; ?>">
    <input type="hidden" name="return_view" value="configtransfer">
    <input type="hidden" name="return_mode" value="<?php echo $isExportMode ? 'export' : 'import'; ?>">
    <?php if ($isExportMode) : ?>
        <input type="hidden" name="cb_require_form_filter" value="1">
        <input type="hidden" name="cb_require_storage_filter" value="1">
    <?php endif; ?>
    <?php echo HTMLHelper::_('form.token'); ?>
</form>

<script>
    (function () {
        function setChecked(selector, checked) {
            var nodes = document.querySelectorAll(selector);
            if (!nodes.length) {
                return;
            }

            for (var i = 0; i < nodes.length; i++) {
                nodes[i].checked = checked;
            }
        }

        function isSectionChecked(sectionKey) {
            var node = document.getElementById('cb_config_section_' + sectionKey);
            return !!(node && node.checked);
        }

        function setGroupState(containerId, itemSelector, enabled) {
            var container = document.getElementById(containerId);
            if (!container) {
                return;
            }

            container.style.opacity = enabled ? '1' : '.55';

            var items = container.querySelectorAll(itemSelector);
            for (var i = 0; i < items.length; i++) {
                items[i].disabled = !enabled;
            }
        }

        function syncSectionFilters() {
            var formFiltersEnabled = isSectionChecked('forms')
                || isSectionChecked('elements')
                || isSectionChecked('list_states')
                || isSectionChecked('resource_access');
            var storageFiltersEnabled = isSectionChecked('storages')
                || isSectionChecked('storage_fields');

            setGroupState('cb-config-forms-box', '.cb-config-form-item', formFiltersEnabled);
            setGroupState('cb-config-storages-box', '.cb-config-storage-item', storageFiltersEnabled);
        }

        function bindCheckButtons(checkButtonId, uncheckButtonId, selector, onChange) {
            var checkButton = document.getElementById(checkButtonId);
            if (checkButton) {
                checkButton.addEventListener('click', function () {
                    setChecked(selector, true);
                    if (typeof onChange === 'function') {
                        onChange();
                    }
                });
            }

            var uncheckButton = document.getElementById(uncheckButtonId);
            if (uncheckButton) {
                uncheckButton.addEventListener('click', function () {
                    setChecked(selector, false);
                    if (typeof onChange === 'function') {
                        onChange();
                    }
                });
            }
        }

        bindCheckButtons('cb-config-sections-check-all', 'cb-config-sections-uncheck-all', '.cb-config-section-toggle', syncSectionFilters);
        bindCheckButtons('cb-config-forms-check-all', 'cb-config-forms-uncheck-all', '.cb-config-form-item');
        bindCheckButtons('cb-config-storages-check-all', 'cb-config-storages-uncheck-all', '.cb-config-storage-item');

        var sectionToggles = document.querySelectorAll('.cb-config-section-toggle');
        for (var i = 0; i < sectionToggles.length; i++) {
            sectionToggles[i].addEventListener('change', syncSectionFilters);
        }

        syncSectionFilters();
    }());
</script>
