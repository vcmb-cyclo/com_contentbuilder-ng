<?php

/**
 * @package     ContentBuilder NG
 * @author      Markus Bopp
 * @link        https://breezingforms-ng.vcmb.fr
 * @copyright   Copyright (C) 2026 by XDA+GIL
 * @license     GNU/GPL
 */

// No direct access
\defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Uri\Uri;
use CB\Component\Contentbuilder_ng\Administrator\Helper\ContentbuilderLegacyHelper;

$frontend = Factory::getApplication()->isClient('site');
$edit_allowed = $frontend ? ContentbuilderLegacyHelper::authorizeFe('edit') : ContentbuilderLegacyHelper::authorize('edit');
$delete_allowed = $frontend ? ContentbuilderLegacyHelper::authorizeFe('delete') : ContentbuilderLegacyHelper::authorize('delete');
$view_allowed = $frontend ? ContentbuilderLegacyHelper::authorizeFe('view') : ContentbuilderLegacyHelper::authorize('view');

$input = Factory::getApplication()->input;
$list = (array) $input->get('list', [], 'array');
$listStart = isset($list['start']) ? $input->getInt('list[start]', 0) : 0;
$listLimit = isset($list['limit']) ? $input->getInt('list[limit]', 0) : 0;
if ($listLimit === 0) {
    $listLimit = (int) Factory::getApplication()->get('list_limit');
}
$listOrdering = isset($list['ordering']) ? $input->getCmd('list[ordering]', '') : '';
$listDirection = isset($list['direction']) ? $input->getCmd('list[direction]', '') : '';
$listQuery = http_build_query(['list' => [
    'start' => $listStart,
    'limit' => $listLimit,
    'ordering' => $listOrdering,
    'direction' => $listDirection,
]]);
$previewQuery = '';
$previewEnabled = $input->getBool('cb_preview', false);
$previewUntil = $input->getInt('cb_preview_until', 0);
$previewSig = (string) $input->getString('cb_preview_sig', '');
$previewActorId = $input->getInt('cb_preview_actor_id', 0);
$previewActorName = (string) $input->getString('cb_preview_actor_name', '');
$isAdminPreview = $input->getBool('cb_preview_ok', false);
$showTopBar = $input->getInt('cb_show_details_top_bar', 1) === 1;
$adminReturnContext = trim((string) $input->getCmd('cb_admin_return', ''));
$adminReturnUrl = Uri::root() . 'administrator/index.php?option=com_contentbuilder_ng&task=form.edit&id=' . (int) $input->getInt('id', 0);
if ($adminReturnContext === 'forms') {
    $adminReturnUrl = Uri::root() . 'administrator/index.php?option=com_contentbuilder_ng&view=forms';
}
$previewFormName = trim((string) ($this->form_name ?? ''));
if ($previewFormName === '') {
    $previewFormName = trim((string) ($this->page_title ?? ''));
}
if ($previewFormName === '') {
    $previewFormName = Text::_('COM_CONTENTBUILDER_NG_NOT_AVAILABLE');
}
$previewFormName = htmlspecialchars($previewFormName, ENT_QUOTES, 'UTF-8');
if ($previewEnabled && $previewUntil > 0 && $previewSig !== '') {
    $previewQuery = '&cb_preview=1'
        . '&cb_preview_until=' . $previewUntil
        . '&cb_preview_actor_id=' . (int) $previewActorId
        . '&cb_preview_actor_name=' . rawurlencode($previewActorName)
        . '&cb_preview_sig=' . rawurlencode($previewSig)
        . ($adminReturnContext !== '' ? '&cb_admin_return=' . rawurlencode($adminReturnContext) : '');
}
$printLink = Route::_('index.php?option=com_contentbuilder_ng&title=' . $input->get('title', '', 'string')
    . ($input->get('tmpl', '', 'string') != '' ? '&tmpl=' . $input->get('tmpl', '', 'string') : '')
    . ($input->get('layout', '', 'string') != '' ? '&layout=' . $input->get('layout', '', 'string') : '')
    . '&task=details.display&layout=print&tmpl=component&id=' . $input->getInt('id', 0)
    . '&record_id=' . $input->getCmd('record_id', 0)
    . $previewQuery);

$wa = Factory::getApplication()->getDocument()->getWebAssetManager();

// Charge le manifeste joomla.asset.json du composant
$wa->getRegistry()->addExtensionRegistryFile('com_contentbuilder_ng');

$wa->useScript('jquery');
$wa->useScript('com_contentbuilder_ng.contentbuilder_ng');
?>

<?php if ($this->author)
    Factory::getApplication()->getDocument()->setMetaData('author', $this->author); ?>
<?php if ($this->robots)
    Factory::getApplication()->getDocument()->setMetaData('robots', $this->robots); ?>
<?php if ($this->rights)
    Factory::getApplication()->getDocument()->setMetaData('rights', $this->rights); ?>
<?php if ($this->metakey)
    Factory::getApplication()->getDocument()->setMetaData('keywords', $this->metakey); ?>
<?php if ($this->metadesc)
    Factory::getApplication()->getDocument()->setMetaData('description', $this->metadesc); ?>
<?php if ($this->xreference)
    Factory::getApplication()->getDocument()->setMetaData('xreference', $this->xreference); ?>

<?php Factory::getApplication()->getDocument()->addStyleDeclaration($this->theme_css); ?>
<?php Factory::getApplication()->getDocument()->addScriptDeclaration($this->theme_js); ?>
<?php
Factory::getApplication()->getDocument()->addStyleDeclaration(
    <<<'CSS'
.cbDetailsWrapper .cbToolBar.cbToolBar--top{
    position:sticky;
    top:var(--cb-details-sticky-top, .5rem);
    z-index:9;
    margin:.25rem 0 .9rem !important;
    padding:.42rem .5rem;
    border:1px solid rgba(36,61,86,.2);
    border-radius:.72rem;
    background:rgba(255,255,255,.96);
    box-shadow:0 .38rem .95rem rgba(16,32,56,.15);
    backdrop-filter:blur(6px);
}
.cbDetailsWrapper .cbToolBar.cbToolBar--top .btn{
    white-space:nowrap;
}
@media (max-width:767.98px){
    .cbDetailsWrapper .cbToolBar.cbToolBar--top{
        top:0;
        padding:.38rem;
    }
    .cbDetailsWrapper .cbToolBar.cbToolBar--top .btn{
        flex:1 1 calc(50% - .5rem);
        justify-content:center;
    }
}
CSS
);
?>
<script type="text/javascript">
    <!--
    function contentbuilder_ng_delete() {
        var confirmed = confirm('<?php echo Text::_('COM_CONTENTBUILDER_NG_CONFIRM_DELETE_MESSAGE'); ?>');
        if (confirmed) {
            location.href = '<?php echo 'index.php?option=com_contentbuilder_ng&title=' . Factory::getApplication()->input->get('title', '', 'string') . (Factory::getApplication()->input->get('tmpl', '', 'string') != '' ? '&tmpl=' . Factory::getApplication()->input->get('tmpl', '', 'string') : '') . (Factory::getApplication()->input->get('layout', '', 'string') != '' ? '&layout=' . Factory::getApplication()->input->get('layout', '', 'string') : '') . '&task=detail.delete&task=edit.display&id=' . Factory::getApplication()->input->getInt('id', 0) . '&cid[]=' . Factory::getApplication()->input->getCmd('record_id', 0) . '&Itemid=' . Factory::getApplication()->input->getInt('Itemid', 0) . ($listQuery !== '' ? '&' . $listQuery : ''); ?>';
        }
    }
    //
    -->
</script>
<?php if (!$showTopBar && $this->print_button): ?>
    <div class="hidden-phone cbPrintBar d-flex justify-content-end mb-2">
        <a
            class="btn btn-sm btn-outline-secondary"
            href="javascript:window.open('<?php echo $printLink; ?>','win2','status=no,toolbar=no,scrollbars=yes,titlebar=no,menubar=no,resizable=yes,width=640,height=480,directories=no,location=no');void(0);"><i
                class="fa fa-print" aria-hidden="true"></i> <?php echo Text::_('JGLOBAL_PRINT'); ?></a>
    </div>
<?php endif; ?>
<div class="cbDetailsWrapper">

<?php if ($isAdminPreview): ?>
    <div class="alert alert-warning d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <span>
            <?php echo Text::_('COM_CONTENTBUILDER_NG_PREVIEW_MODE') . ' - ' . Text::sprintf('COM_CONTENTBUILDER_NG_PREVIEW_CURRENT_FORM', $previewFormName) . ' - ' . Text::sprintf('COM_CONTENTBUILDER_NG_PREVIEW_CONFIG_TAB', Text::_('COM_CONTENTBUILDER_NG_PREVIEW_TAB_CONTENT_TEMPLATE')); ?>
        </span>
        <a class="btn btn-sm btn-outline-secondary" href="<?php echo $adminReturnUrl; ?>">
            <span class="fa-solid fa-arrow-left me-1" aria-hidden="true"></span>
            <?php echo Text::_('COM_CONTENTBUILDER_NG_BACK_TO_ADMIN'); ?>
        </a>
    </div>
<?php endif; ?>

<?php
$prevRecordId = property_exists($this, 'prev_record_id') ? (int) $this->prev_record_id : 0;
$nextRecordId = property_exists($this, 'next_record_id') ? (int) $this->next_record_id : 0;
$detailsNavBaseLink = 'index.php?option=com_contentbuilder_ng&title=' . $input->get('title', '', 'string')
    . '&task=details.display&id=' . $input->getInt('id', 0)
    . (Factory::getApplication()->input->get('tmpl', '', 'string') != '' ? '&tmpl=' . Factory::getApplication()->input->get('tmpl', '', 'string') : '')
    . (Factory::getApplication()->input->get('layout', '', 'string') != '' ? '&layout=' . Factory::getApplication()->input->get('layout', '', 'string') : '')
    . '&Itemid=' . $input->getInt('Itemid', 0)
    . ($listQuery !== '' ? '&' . $listQuery : '')
    . $previewQuery;
$showCloseButton = $this->show_back_button && Factory::getApplication()->input->getBool('cb_show_details_back_button', 1);
$closeListLink = Route::_('index.php?option=com_contentbuilder_ng&title=' . Factory::getApplication()->input->get('title', '', 'string') . '&view=list&task=list.display&id=' . Factory::getApplication()->input->getInt('id', 0) . (Factory::getApplication()->input->get('tmpl', '', 'string') != '' ? '&tmpl=' . Factory::getApplication()->input->get('tmpl', '', 'string') : '') . (Factory::getApplication()->input->get('layout', '', 'string') != '' ? '&layout=' . Factory::getApplication()->input->get('layout', '', 'string') : '') . ($listQuery !== '' ? '&' . $listQuery : '') . '&Itemid=' . Factory::getApplication()->input->getInt('Itemid', 0) . $previewQuery);
$showActionToolbar = (
    (Factory::getApplication()->input->getInt('cb_show_details_back_button', 1) && $this->show_back_button)
    || $delete_allowed
    || $edit_allowed
    || ($showTopBar && ($this->print_button || $prevRecordId > 0 || $nextRecordId > 0 || $showCloseButton))
);
$showAuditTrail = $input->getInt('cb_show_author', 1) === 1;

$createdOnText = '';
if (!empty($this->created)) {
    $createdOnText = Text::_('COM_CONTENTBUILDER_NG_CREATED_ON') . ' ' . HTMLHelper::_('date', $this->created, Text::_('DATE_FORMAT_LC5'));
}

$createdByText = '';
if (!empty($this->created_by)) {
    $createdByText = Text::_('COM_CONTENTBUILDER_NG_BY') . ' ' . htmlentities((string) $this->created_by, ENT_QUOTES, 'UTF-8');
}

$modifiedOnText = '';
if (!empty($this->modified)) {
    $modifiedOnText = Text::_('COM_CONTENTBUILDER_NG_LAST_UPDATED_ON') . ' ' . HTMLHelper::_('date', $this->modified, Text::_('DATE_FORMAT_LC5'));
}

$modifiedByText = '';
if (!empty($this->modified_by)) {
    $modifiedByText = Text::_('COM_CONTENTBUILDER_NG_BY') . ' ' . htmlentities((string) $this->modified_by, ENT_QUOTES, 'UTF-8');
}

$createdTrailText = trim($createdOnText . (($createdOnText !== '' && $createdByText !== '') ? ' ' : '') . $createdByText);
$modifiedTrailText = trim($modifiedOnText . (($modifiedOnText !== '' && $modifiedByText !== '') ? ' ' : '') . $modifiedByText);
?>

<?php
if ($this->show_page_heading && $this->page_title) {
?>
    <h1 class="display-6 mb-4">
        <?php if (!$showTopBar && ($prevRecordId > 0 || $nextRecordId > 0 || $showCloseButton)): ?>
            <span class="cbTitleRecordNav d-inline-flex flex-wrap gap-2 float-end ms-2 mb-2">
                <?php if ($prevRecordId > 0): ?>
                    <a
                        class="btn btn-sm btn-outline-secondary cbButton cbBackButton cbPrevButton"
                        href="<?php echo Route::_($detailsNavBaseLink . '&record_id=' . $prevRecordId); ?>"
                        title="<?php echo Text::_('JPREVIOUS'); ?>">
                        <span class="fa-solid fa-arrow-left me-1" aria-hidden="true"></span>
                        <?php echo Text::_('JPREVIOUS'); ?>
                    </a>
                <?php endif; ?>
                <?php if ($nextRecordId > 0): ?>
                    <a
                        class="btn btn-sm btn-outline-secondary cbButton cbBackButton cbNextButton"
                        href="<?php echo Route::_($detailsNavBaseLink . '&record_id=' . $nextRecordId); ?>"
                        title="<?php echo Text::_('JNEXT'); ?>">
                        <?php echo Text::_('JNEXT'); ?>
                        <span class="fa-solid fa-arrow-right ms-1" aria-hidden="true"></span>
                    </a>
                <?php endif; ?>
                <?php if ($showCloseButton): ?>
                    <a
                        class="btn btn-sm btn-outline-secondary cbButton cbBackButton cbCloseButton"
                        href="<?php echo $closeListLink; ?>"
                        title="<?php echo Text::_('COM_CONTENTBUILDER_NG_CLOSE'); ?>">
                        <span class="fa-solid fa-xmark me-1" aria-hidden="true"></span>
                        <?php echo Text::_('COM_CONTENTBUILDER_NG_CLOSE'); ?>
                    </a>
                <?php endif; ?>
            </span>
        <?php endif; ?>
        <?php echo $this->page_title; ?>
    </h1>
<?php
}
?>
<?php echo $this->event->afterDisplayTitle; ?>

<?php
ob_start();
?>

<?php
if ($showActionToolbar) {
?>

    <div class="cbToolBar d-flex justify-content-end gap-2 flex-wrap mb-3">
    <?php
}
    ?>

    <?php if ($showTopBar && $this->print_button): ?>
        <a
            class="hidden-phone btn btn-sm btn-outline-secondary cbButton cbPrintButton"
            href="javascript:window.open('<?php echo $printLink; ?>','win2','status=no,toolbar=no,scrollbars=yes,titlebar=no,menubar=no,resizable=yes,width=640,height=480,directories=no,location=no');void(0);"
            title="<?php echo Text::_('JGLOBAL_PRINT'); ?>">
            <i class="fa fa-print" aria-hidden="true"></i>
            <?php echo Text::_('JGLOBAL_PRINT'); ?>
        </a>
    <?php endif; ?>

    <?php if ($showTopBar && $prevRecordId > 0): ?>
        <a
            class="btn btn-sm btn-outline-secondary cbButton cbBackButton cbPrevButton"
            href="<?php echo Route::_($detailsNavBaseLink . '&record_id=' . $prevRecordId); ?>"
            title="<?php echo Text::_('JPREVIOUS'); ?>">
            <span class="fa-solid fa-arrow-left me-1" aria-hidden="true"></span>
            <?php echo Text::_('JPREVIOUS'); ?>
        </a>
    <?php endif; ?>

    <?php if ($showTopBar && $nextRecordId > 0): ?>
        <a
            class="btn btn-sm btn-outline-secondary cbButton cbBackButton cbNextButton"
            href="<?php echo Route::_($detailsNavBaseLink . '&record_id=' . $nextRecordId); ?>"
            title="<?php echo Text::_('JNEXT'); ?>">
            <?php echo Text::_('JNEXT'); ?>
            <span class="fa-solid fa-arrow-right ms-1" aria-hidden="true"></span>
        </a>
    <?php endif; ?>

    <?php if ($showTopBar && $showCloseButton): ?>
        <a
            class="btn btn-sm btn-outline-secondary cbButton cbBackButton cbCloseButton"
            href="<?php echo $closeListLink; ?>"
            title="<?php echo Text::_('COM_CONTENTBUILDER_NG_CLOSE'); ?>">
            <span class="fa-solid fa-xmark me-1" aria-hidden="true"></span>
            <?php echo Text::_('COM_CONTENTBUILDER_NG_CLOSE'); ?>
        </a>
    <?php endif; ?>

    <?php if ($edit_allowed) { ?>
        <a class="btn btn-sm btn-primary cbButton cbEditButton"
            href="<?php echo Route::_('index.php?option=com_contentbuilder_ng&task=edit.display&id=' . Factory::getApplication()->input->getInt('id', 0) . '&record_id=' . Factory::getApplication()->input->getCmd('record_id', 0) . (Factory::getApplication()->input->get('tmpl', '', 'string') != '' ? '&tmpl=' . Factory::getApplication()->input->get('tmpl', '', 'string') : '') . '&Itemid=' . Factory::getApplication()->input->getInt('Itemid', 0) . (Factory::getApplication()->input->get('layout', '', 'string') != '' ? '&layout=' . Factory::getApplication()->input->get('layout', '', 'string') : '') . ($listQuery !== '' ? '&' . $listQuery : '') . $previewQuery); ?>"
            title="<?php echo Text::_('COM_CONTENTBUILDER_NG_EDIT'); ?>">
            <span class="fa-solid fa-pen me-1" aria-hidden="true"></span>
            <?php echo Text::_('COM_CONTENTBUILDER_NG_EDIT') ?>
        </a>
    <?php
    }
    ?>
    <?php if ($delete_allowed) { ?>
        <button class="btn btn-sm btn-outline-danger cbButton cbDeleteButton d-inline-flex align-items-center gap-1 rounded-pill" onclick="contentbuilder_ng_delete();"
            title="<?php echo Text::_('COM_CONTENTBUILDER_NG_DELETE'); ?>">
            <span class="fa-solid fa-trash" aria-hidden="true"></span>
            <span><?php echo Text::_('COM_CONTENTBUILDER_NG_DELETE') ?></span>
        </button>
    <?php
    }
    ?>
    <?php if (!$showTopBar && $showCloseButton && (!$this->show_page_heading || !$this->page_title)): ?>
        <a class="btn btn-sm btn-outline-secondary cbButton cbBackButton"
            href="<?php echo $closeListLink; ?>"
            title="<?php echo Text::_('COM_CONTENTBUILDER_NG_CLOSE'); ?>">
            <span class="fa-solid fa-xmark me-1" aria-hidden="true"></span>
            <?php echo Text::_('COM_CONTENTBUILDER_NG_CLOSE') ?>
        </a>
    <?php endif; ?>

    <?php
    if ($showActionToolbar) {
    ?>

    </div>

<?php
    }
?>

<?php
$buttons = ob_get_contents();
ob_end_clean();

if ($showTopBar) {
?>
    <div style="clear:right;"></div>
<?php
    if ($buttons !== '') {
        echo str_replace('class="cbToolBar ', 'class="cbToolBar cbToolBar--top ', $buttons);
    }
}
?>

<div class="cbDetailsBody">
<?php echo $this->event->beforeDisplayContent; ?>
<?php echo $this->toc ?>
<?php echo $this->tpl ?>
<?php echo $this->event->afterDisplayContent; ?>
</div>


<?php if ($showAuditTrail && ($createdTrailText !== '' || $modifiedTrailText !== '')) : ?>
    <div class="cbAuditTrail mt-2 mb-2">
        <?php if ($createdTrailText !== '') : ?>
            <span class="small created-by"><?php echo $createdTrailText; ?></span>
        <?php endif; ?>
        <?php if ($modifiedTrailText !== '') : ?>
            <span class="small created-by"><?php echo $modifiedTrailText; ?></span>
        <?php endif; ?>
    </div>
<?php endif; ?>

<br />

<?php
if (Factory::getApplication()->input->getInt('cb_show_details_bottom_bar', 1)) {
    if ($buttons !== '') {
        echo str_replace('class="cbToolBar ', 'class="cbToolBar cbToolBar--bottom ', $buttons);
    }
?>
    <div style="clear:right;"></div>
<?php
}
?>

</div>
