<?php

/**
 * @package     ContentBuilder NG
 * @author      Markus Bopp / XDA+GIL
 * @link        https://breezingforms-ng.vcmb.fr
 * @copyright   Copyright © 2026 by XDA+GIL
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

\defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;

$item = $displayData['item'] ?? null;
$form = $displayData['form'] ?? null;
$allElements = is_array($displayData['allElements'] ?? null) ? $displayData['allElements'] : [];
$renderCheckbox = $displayData['renderCheckbox'] ?? null;
$editablePrepareSnippetOptions = is_array($displayData['editablePrepareSnippetOptions'] ?? null) ? $displayData['editablePrepareSnippetOptions'] : [];
$prepareEffectOptions = is_array($displayData['prepareEffectOptions'] ?? null) ? $displayData['prepareEffectOptions'] : [];
$isBreezingFormsType = !empty($displayData['isBreezingFormsType']);

$renderBooleanButtonGroup = static function (
    string $name,
    string $idPrefix,
    bool $isEnabled,
    string $labelText
): string {
    $yesId = $idPrefix . '_yes';
    $noId = $idPrefix . '_no';

    return '<div class="btn-group btn-group-sm" role="group" aria-label="' . htmlspecialchars($labelText, ENT_QUOTES, 'UTF-8') . '">'
        . '<input class="btn-check" type="radio" name="' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '" id="' . htmlspecialchars($yesId, ENT_QUOTES, 'UTF-8') . '" value="1"' . ($isEnabled ? ' checked="checked"' : '') . ' />'
        . '<label class="btn btn-outline-secondary" for="' . htmlspecialchars($yesId, ENT_QUOTES, 'UTF-8') . '">' . Text::_('COM_CONTENTBUILDERNG_YES') . '</label>'
        . '<input class="btn-check" type="radio" name="' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '" id="' . htmlspecialchars($noId, ENT_QUOTES, 'UTF-8') . '" value="0"' . (!$isEnabled ? ' checked="checked"' : '') . ' />'
        . '<label class="btn btn-outline-secondary" for="' . htmlspecialchars($noId, ENT_QUOTES, 'UTF-8') . '">' . Text::_('COM_CONTENTBUILDERNG_NO') . '</label>'
        . '</div>';
};

$renderInlineBooleanControl = static function (
    string $labelHtml,
    string $controlHtml
): string {
    return '<div class="d-flex flex-wrap align-items-center gap-2 gap-md-3">'
        . '<div>' . $labelHtml . '</div>'
        . '<div>' . $controlHtml . '</div>'
        . '</div>';
};

$prepareExamplesText = <<<'TXT'
// Ici, vous pouvez modifier les libellés et les valeurs de chaque élément avant le rendu du template d'édition.

// Adaptez la valeur et le libellé avec du code PHP.
// Les données sont stockées dans le tableau $items.

// Exemple : la valeur du champ "NAME" sera affichée en majuscules, en gras et en rouge.
$items["NAME"]["value"] = strtoupper((string) $items["NAME"]["value"]);
$items["NAME"]["value"] = "<b>" . $items["NAME"]["value"] . "</b>";
$items["NAME"]["value"] = "<span style=\"color:#dc3545\">" . $items["NAME"]["value"] . "</span>";

// Exemple : la valeur du champ "COUNT" sera affichée en rouge si elle est < 0.
$items["COUNT"]["value"] = (is_numeric((string) $items["COUNT"]["value"]) && (float) $items["COUNT"]["value"] < 0)
    ? "<span style=\"color:#dc3545\">" . $items["COUNT"]["value"] . "</span>"
    : $items["COUNT"]["value"];

// Exemple : ajouter la date courante à un champ de libellé.
$items["DATE_LABEL"]["label"] = (string) $items["DATE_LABEL"]["label"] . " (" . date("Y-m-d") . ")";
TXT;
?>
<h3 id="cb-form-details-display" class="mb-3">
    <?php echo Text::_('COM_CONTENTBUILDERNG_TAB_DETAILS_DISPLAY'); ?>
</h3>
<p class="text-muted mb-3">
    <?php echo Text::_('COM_CONTENTBUILDERNG_TAB_DETAILS_DISPLAY_INTRO'); ?>
</p>
<div class="alert alert-info mb-3">
    <?php echo Text::_('COM_CONTENTBUILDERNG_TAB_DETAILS_DISPLAY_PERMISSION_HINT'); ?>
</div>
<div class="row gx-3 gy-1 mt-0 align-items-stretch mb-3">
    <div class="col-12 col-xl-4 d-flex" id="cb-form-details-show-buttons">
        <div class="border rounded bg-body p-3 d-flex flex-column flex-grow-1">
            <h4 class="h6 text-body-secondary mb-2">
                <?php echo Text::_('COM_CONTENTBUILDERNG_SHOW_BUTTON_OPTIONS'); ?>
            </h4>
            <div class="d-flex flex-wrap align-items-center gap-3">
                <div>
                    <input type="hidden" name="jform[cb_show_details_top_bar]" value="0" />
                    <?php echo $renderCheckbox('jform[cb_show_details_top_bar]', 'cb_show_details_top_bar', (bool) ($item->cb_show_details_top_bar ?? true)); ?>
                    <label class="form-check-label" for="cb_show_details_top_bar">
                        <span class="editlinktip hasTip" title="<?php echo Text::_('COM_CONTENTBUILDERNG_SHOW_DETAILS_TOP_BAR_DESC'); ?>">
                            <?php echo Text::_('COM_CONTENTBUILDERNG_SHOW_DETAILS_TOP_BAR'); ?>
                        </span>
                    </label>
                </div>
                <div>
                    <input type="hidden" name="jform[cb_show_details_bottom_bar]" value="0" />
                    <?php echo $renderCheckbox('jform[cb_show_details_bottom_bar]', 'cb_show_details_bottom_bar', (bool) ($item->cb_show_details_bottom_bar ?? false)); ?>
                    <label class="form-check-label" for="cb_show_details_bottom_bar">
                        <span class="editlinktip hasTip" title="<?php echo Text::_('COM_CONTENTBUILDERNG_SHOW_DETAILS_BOTTOM_BAR_DESC'); ?>">
                            <?php echo Text::_('COM_CONTENTBUILDERNG_SHOW_DETAILS_BOTTOM_BAR'); ?>
                        </span>
                    </label>
                </div>
            </div>
        </div>
    </div>
</div>
<div class="row g-3 mb-4">
    <div class="col-12 col-xl-6 d-flex">
        <div class="border rounded bg-body p-3 d-flex flex-column flex-grow-1">
            <h4 class="h6 text-body-secondary mb-3">
                <?php echo Text::_('COM_CONTENTBUILDERNG_CREATE'); ?>
            </h4>
            <div class="row g-3 align-items-end">
                <div class="col-12 col-md-auto">
                    <input type="hidden" name="jform[create_sample]" id="cb_create_sample_flag" value="0" />
                    <div class="d-flex flex-wrap align-items-center gap-2">
                        <button type="button" class="btn btn-primary d-inline-flex align-items-center gap-1" id="create_sample"
                            title="<?php echo Text::_('COM_CONTENTBUILDERNG_CREATE_TEMPLATE') . ' - ' . Text::_('COM_CONTENTBUILDERNG_CREATE_TEMPLATE_TIP'); ?>"
                            aria-label="<?php echo Text::_('COM_CONTENTBUILDERNG_CREATE_TEMPLATE') . ' - ' . Text::_('COM_CONTENTBUILDERNG_CREATE_TEMPLATE_TIP'); ?>"
                            onclick="cbQueueDetailsSampleGeneration(this);">
                            <span class="fa-solid fa-wand-magic-sparkles" aria-hidden="true"></span>
                            <?php echo Text::_('COM_CONTENTBUILDERNG_CREATE_TEMPLATE'); ?>
                        </button>
                        <small id="cb_create_sample_hint" class="text-success d-none">
                            <?php echo Text::_('COM_CONTENTBUILDERNG_INITIALISE_WILL_APPLY_ON_SAVE'); ?>
                        </small>
                    </div>
                </div>
                <div class="col-12 col-md-auto ms-md-4">
                    <?php
                    echo $renderInlineBooleanControl(
                        '<span class="form-label mb-0 editlinktip hasTip" title="' . htmlspecialchars(Text::_('COM_CONTENTBUILDERNG_CREATE_TIP'), ENT_QUOTES, 'UTF-8') . '">' . Text::_('COM_CONTENTBUILDERNG_CREATE_ARTICLES_LABEL') . '</span>',
                        $renderBooleanButtonGroup('jform[create_articles]', 'create_articles', (int) ($item->create_articles ?? 0) === 1, Text::_('COM_CONTENTBUILDERNG_CREATE_ARTICLES_LABEL'))
                    );
                    ?>
                </div>
                <div class="col-12 col-md-auto ms-md-2">
                    <?php
                    echo $renderInlineBooleanControl(
                        '<span class="form-label mb-0 editlinktip hasTip" title="' . htmlspecialchars(Text::_('COM_CONTENTBUILDERNG_DELETE_ARTICLES_TIP'), ENT_QUOTES, 'UTF-8') . '">' . Text::_('COM_CONTENTBUILDERNG_DELETE_ARTICLES') . '</span>',
                        $renderBooleanButtonGroup('jform[delete_articles]', 'delete_articles', !empty($item->delete_articles), Text::_('COM_CONTENTBUILDERNG_DELETE_ARTICLES'))
                    );
                    ?>
                </div>
            </div>
        </div>
    </div>
    <div class="col-12 col-xl-6 d-flex">
        <div class="border rounded bg-body p-3 d-flex flex-column flex-grow-1">
            <h4 class="h6 text-body-secondary mb-3">
                <?php echo Text::_('JDEFAULT'); ?>
            </h4>
            <div class="row g-3">
                <div class="col-12 col-md-6">
                    <label for="title_field" class="form-label mb-1">
                        <span class="editlinktip hasTip" title="<?php echo Text::_('COM_CONTENTBUILDERNG_TITLE_FIELD_TIP'); ?>">
                            <?php echo Text::_('COM_CONTENTBUILDERNG_TITLE_FIELD'); ?>
                        </span>
                    </label>
                    <select class="form-select form-select-sm" style="max-width: 400px;" name="jform[title_field]" id="title_field">
                        <option value="0">
                            - <?php echo Text::_('COM_CONTENTBUILDERNG_NONE'); ?> -
                        </option>
                        <?php foreach ($allElements as $sortable) : ?>
                            <option value="<?php echo $sortable->reference_id; ?>" <?php echo ($item->title_field ?? null) == $sortable->reference_id ? ' selected="selected"' : ''; ?>>
                                <?php echo htmlentities($sortable->label ?? '', ENT_QUOTES, 'UTF-8'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12 col-md-6">
                    <label for="default_category" class="form-label mb-1">
                        <span class="editlinktip hasTip" title="<?php echo Text::_('COM_CONTENTBUILDERNG_DEFAULT_CATEGORY_TIP'); ?>">
                            <?php echo Text::_('COM_CONTENTBUILDERNG_DEFAULT_CATEGORY'); ?>
                        </span>
                    </label>
                    <select class="form-select form-select-sm" style="max-width: 400px;" id="default_category" name="jform[sectioncategories]">
                        <?php foreach ((array) ($item->sectioncategories ?? []) as $category) : ?>
                            <option <?php echo ($item->default_category ?? null) == $category->value ? ' selected="selected"' : ''; ?> value="<?php echo $category->value; ?>">
                                <?php echo htmlentities($category->text ?? '', ENT_QUOTES, 'UTF-8'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12 col-md-6">
                    <label for="default_access" class="form-label mb-1">
                        <span class="editlinktip hasTip" title="<?php echo Text::_('COM_CONTENTBUILDERNG_DEFAULT_ACCESS_TIP'); ?>">
                            <?php echo Text::_('COM_CONTENTBUILDERNG_DEFAULT_ACCESS'); ?>
                        </span>
                    </label>
                    <?php echo HTMLHelper::_('access.level', 'default_access', $item->default_access, '', [], 'default_access'); ?>
                </div>
                <div class="col-12 col-md-6">
                    <?php
                    echo $renderInlineBooleanControl(
                        '<span class="form-label mb-0 editlinktip hasTip" title="' . htmlspecialchars(Text::_('COM_CONTENTBUILDERNG_DEFAULT_FEATURED_TIP'), ENT_QUOTES, 'UTF-8') . '">' . Text::_('COM_CONTENTBUILDERNG_DEFAULT_FEATURED') . '</span>',
                        $renderBooleanButtonGroup('jform[default_featured]', 'default_featured', !empty($item->default_featured), Text::_('COM_CONTENTBUILDERNG_DEFAULT_FEATURED'))
                    );
                    ?>
                </div>
            </div>
        </div>
    </div>
    <div class="col-12 col-xl-6 d-flex">
        <div class="border rounded bg-body p-3 d-flex flex-column flex-grow-1">
            <h4 class="h6 text-body-secondary mb-3">
                <?php echo Text::_('COM_CONTENTBUILDERNG_LANGUAGE'); ?>
            </h4>
            <div class="row g-3">
                <div class="col-12">
                    <label for="default_lang_code" class="form-label mb-1">
                        <span class="editlinktip hasTip" title="<?php echo Text::_('COM_CONTENTBUILDERNG_DEFAULT_LANG_CODE_TIP'); ?>">
                            <?php echo Text::_('COM_CONTENTBUILDERNG_DEFAULT_LANG_CODE'); ?>
                        </span>
                    </label>
                    <select class="form-select form-select-sm" style="max-width: 180px;" name="jform[default_lang_code]" id="default_lang_code">
                        <option value="*">
                            <?php echo Text::_('COM_CONTENTBUILDERNG_ANY'); ?>
                        </option>
                        <?php foreach ((array) ($item->language_codes ?? []) as $langCode) : ?>
                            <option value="<?php echo $langCode; ?>" <?php echo $langCode == ($item->default_lang_code ?? null) ? ' selected="selected"' : ''; ?>>
                                <?php echo $langCode; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12 col-md-6">
                    <?php
                    echo $renderInlineBooleanControl(
                        '<span class="form-label mb-0 editlinktip hasTip" title="' . htmlspecialchars(Text::_('COM_CONTENTBUILDERNG_ARTICLE_RECORD_IMPACT_TIP'), ENT_QUOTES, 'UTF-8') . '">' . Text::_('COM_CONTENTBUILDERNG_ARTICLE_RECORD_IMPACT') . '</span>',
                        $renderBooleanButtonGroup('jform[article_record_impact_language]', 'article_record_impact_language', !empty($item->article_record_impact_language), Text::_('COM_CONTENTBUILDERNG_ARTICLE_RECORD_IMPACT'))
                    );
                    ?>
                </div>
                <div class="col-12 col-md-6">
                    <?php
                    echo $renderInlineBooleanControl(
                        '<span class="form-label mb-0 editlinktip hasTip" title="' . htmlspecialchars(Text::_('COM_CONTENTBUILDERNG_DEFAULT_LANG_CODE_IGNORE_TIP'), ENT_QUOTES, 'UTF-8') . '">' . Text::_('COM_CONTENTBUILDERNG_DEFAULT_LANG_CODE_IGNORE') . '</span>',
                        $renderBooleanButtonGroup('jform[default_lang_code_ignore]', 'default_lang_code_ignore', !empty($item->default_lang_code_ignore), Text::_('COM_CONTENTBUILDERNG_DEFAULT_LANG_CODE_IGNORE'))
                    );
                    ?>
                </div>
            </div>
        </div>
    </div>
    <div class="col-12 col-xl-6 d-flex">
        <div class="border rounded bg-body p-3 d-flex flex-column flex-grow-1">
            <h4 class="h6 text-body-secondary mb-3">
                <?php echo Text::_('COM_CONTENTBUILDERNG_PUBLISH'); ?>
            </h4>
            <div class="row g-3">
                <div class="col-12 col-md-4">
                    <label for="default_publish_up_days" class="form-label mb-1">
                        <span class="editlinktip hasTip" title="<?php echo Text::_('COM_CONTENTBUILDERNG_DEFAULT_PUBLISH_UP_DAYS_TIP'); ?>">
                            <?php echo Text::_('COM_CONTENTBUILDERNG_DEFAULT_PUBLISH_UP_DAYS'); ?>
                        </span>
                    </label>
                    <input class="form-control form-control-sm" style="max-width: 130px;" type="text" name="jform[default_publish_up_days]"
                        id="default_publish_up_days" value="<?php echo htmlspecialchars((string) ($item->default_publish_up_days ?? ''), ENT_QUOTES, 'UTF-8'); ?>" />
                </div>
                <div class="col-12 col-md-4">
                    <label for="default_publish_down_days" class="form-label mb-1">
                        <span class="editlinktip hasTip" title="<?php echo Text::_('COM_CONTENTBUILDERNG_DEFAULT_PUBLISH_DOWN_DAYS_TIP'); ?>">
                            <?php echo Text::_('COM_CONTENTBUILDERNG_DEFAULT_PUBLISH_DOWN_DAYS'); ?>
                        </span>
                    </label>
                    <input class="form-control form-control-sm" style="max-width: 130px;" type="text" name="jform[default_publish_down_days]"
                        id="default_publish_down_days" value="<?php echo htmlspecialchars((string) ($item->default_publish_down_days ?? ''), ENT_QUOTES, 'UTF-8'); ?>" />
                </div>
                <div class="col-12 col-md-6">
                    <?php
                    echo $renderInlineBooleanControl(
                        '<span class="form-label mb-0 editlinktip hasTip" title="' . htmlspecialchars(Text::_('COM_CONTENTBUILDERNG_ARTICLE_RECORD_PUBLISH_IMPACT_TIP'), ENT_QUOTES, 'UTF-8') . '">' . Text::_('COM_CONTENTBUILDERNG_ARTICLE_RECORD_PUBLISH_IMPACT') . '</span>',
                        $renderBooleanButtonGroup('jform[article_record_impact_publish]', 'article_record_impact_publish', !empty($item->article_record_impact_publish), Text::_('COM_CONTENTBUILDERNG_ARTICLE_RECORD_PUBLISH_IMPACT'))
                    );
                    ?>
                </div>
                <div class="col-12 col-md-4">
                    <input type="hidden" name="jform[auto_publish]" value="0" />
                    <div class="form-check mb-0 ps-0 d-flex align-items-center">
                        <span class="editlinktip hasTip" title="<?php echo htmlspecialchars(Text::_('COM_CONTENTBUILDERNG_AUTO_PUBLISH_TIP'), ENT_QUOTES, 'UTF-8'); ?>">
                            <?php echo is_callable($renderCheckbox) ? $renderCheckbox('jform[auto_publish]', 'auto_publish', (int) $item->auto_publish === 1) : ''; ?>
                        </span>
                        <label class="form-check-label" for="auto_publish">
                            <?php echo Text::_('COM_CONTENTBUILDERNG_AUTO_PUBLISH'); ?>
                        </label>
                    </div>
                </div>
                <?php if (!empty($item->edit_by_type) && $isBreezingFormsType) : ?>
                    <div class="col-12 col-md-6">
                        <input type="hidden" name="jform[protect_upload_directory]" value="0" />
                        <?php
                        echo $renderInlineBooleanControl(
                            '<span class="form-label mb-0 editlinktip hasTip" title="' . htmlspecialchars(Text::_('COM_CONTENTBUILDERNG_UPLOAD_DIRECTORY_TYPE_TIP'), ENT_QUOTES, 'UTF-8') . '">' . Text::_('COM_CONTENTBUILDERNG_PROTECT_UPLOAD_DIRECTORY') . '</span>',
                            '<div class="form-check mb-0 ps-0">'
                            . (is_callable($renderCheckbox) ? $renderCheckbox('jform[protect_upload_directory]', 'protect_upload_directory', trim((string) $item->protect_upload_directory) !== '') : '')
                            . '<label class="form-check-label" for="protect_upload_directory">' . Text::_('COM_CONTENTBUILDERNG_PROTECT_UPLOAD_DIRECTORY') . '</label>'
                            . '</div>'
                        );
                        ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php echo $form ? $form->renderField('details_template') : ''; ?>
<hr />
<h3 id="cb-form-details-prepare" class="mb-3">
    <?php echo Text::_('COM_CONTENTBUILDERNG_DETAILS_PREPARE_MODE_TITLE'); ?>
</h3>
<?php
echo LayoutHelper::render(
    'form.prepare_editor',
    [
        'snippetOptions' => $editablePrepareSnippetOptions,
        'effectOptions' => $prepareEffectOptions,
        'selectId' => 'cb_details_prepare_snippet_select',
        'slotName' => 'cb_details_prepare_slot',
        'slotValueId' => 'cb_details_prepare_slot_value',
        'slotLabelId' => 'cb_details_prepare_slot_label',
        'effectSelectId' => 'cb_details_prepare_effect_select',
        'addButtonId' => 'cb_add_details_prepare_snippet',
        'addButtonOnclick' => 'cbInsertDetailsPrepareSnippet();',
        'hintId' => 'cb_details_prepare_snippet_hint',
        'fieldName' => 'jform[details_prepare]',
        'editorId' => 'jform_details_prepare',
        'value' => (string) ($item->details_prepare ?? ''),
        'emptyValue' => '// Ici, vous pouvez modifier les libellés et les valeurs de chaque élément avant le rendu du template détail.' . "\n",
        'addButtonTextKey' => 'COM_CONTENTBUILDERNG_DETAILS_PREPARE_SNIPPET_ADD',
        'showExamplesModal' => true,
        'examplesText' => $prepareExamplesText,
    ],
    JPATH_COMPONENT_ADMINISTRATOR . '/layouts'
);
?>
