<?php
/**
 * @package     ContentBuilder NG
 * @author      Markus Bopp / XDA+GIL
 * @link        https://breezingforms-ng.vcmb.fr
 * @copyright   (C) 2026 by XDA+GIL
 * @license     GNU/GPL
*/

namespace CB\Component\Contentbuilder_ng\Administrator\View\Storage;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\CMS\Uri\Uri;
use Joomla\Database\DatabaseInterface;
use CB\Component\Contentbuilder_ng\Administrator\View\Contentbuilder_ng\HtmlView as BaseHtmlView;

class HtmlView extends BaseHtmlView
{
    public $form;
    public $fields;
    public $tables;
    public $pagination;
    public $ordering;
    public $item;
    public $state;
    public bool $frontend = false;
    public ?int $storageRecordsCount = null;

    public function display($tpl = null): void
    {         
        if ($this->getLayout() === 'help') {
            parent::display($tpl);
            return;
        }

        $app = Factory::getApplication();
        $app->input->set('hidemainmenu', true);

        // JS
        $wa = $app->getDocument()->getWebAssetManager();
        $wa->getRegistry()->addExtensionRegistryFile('com_contentbuilder_ng');

		if (!$this->frontend) {
            // 1️⃣ Récupération du WebAssetManager
            $document = $this->getDocument();
            $wa = $document->getWebAssetManager();
            $wa->addInlineStyle(
                '.icon-logo_left{
                    background-image:url(' . Uri::root(true) . '/media/com_contentbuilder_ng/images/logo_left.png);
                    background-size:contain;
                    background-repeat:no-repeat;
                    background-position:center;
                    display:inline-block;
                    width:48px;
                    height:48px;
                    vertical-align:middle;
                }'
            );
        }    
            
        // Formulaire JForm
        $this->form = $this->getModel()->getForm();

        // Données (l’item)
        $this->item = $this->getModel()->getItem();
        $this->storageRecordsCount = $this->getStorageRecordsCount($this->item);

        $this->tables     = $this->get('DbTables');

        // Chargement sécurisé des éléments
        $storageId = (int) ($this->item->id ?? $app->input->getInt('id', 0));

        $this->fields = [];
        $this->pagination = null;
        $this->state = null;

        try {
            $storageId  = (int) ($this->item->id ?? $app->input->getInt('id', 0));
            if ($storageId > 0) {
                $factory = $app->bootComponent('com_contentbuilder_ng')->getMVCFactory();
                $fieldsModel = $factory->createModel('Storagefields', 'Administrator');

                if (!$fieldsModel) {
                    throw new \RuntimeException('Modèle Storagefields introuvable (factory)');
                }

                // IMPORTANT : fournir le form id au ListModel
                $fieldsModel->setStorageId($storageId);

                // Charge les items
                $this->fields     = $fieldsModel->getItems();
                $this->pagination = $fieldsModel->getPagination();
                $this->state      = $fieldsModel->getState();
                $this->ordering   = ($this->state && $this->state->get('list.ordering') === 'ordering');
            }
        } catch (\Throwable $e) {
            $app->enqueueMessage(
                'Erreur lors du chargement des champs : ' . $e->getMessage(),
                'warning'
            );
        }

        $isNew = ((int) ($this->item->id ?? 0) < 1);
        $text  = $isNew ? Text::_('COM_CONTENTBUILDER_NG_NEW') : Text::_('COM_CONTENTBUILDER_NG_EDIT');

        ToolbarHelper::title(
            Text::_('COM_CONTENTBUILDER_NG') .' :: ' . ($isNew ? Text::_('COM_CONTENTBUILDER_NG_STORAGES') : ($this->item->title ?? ''))
            . ' : <small><small>[ ' . $text . ' ]</small></small>',
            'logo_left'
        );

        ToolbarHelper::saveGroup(
            [
                ['apply', 'storage.apply', 'JTOOLBAR_APPLY'],
                ['save', 'storage.save', 'JTOOLBAR_SAVE'],
                ['save2new', 'storage.save2new', 'JTOOLBAR_SAVE_AND_NEW'],
            ],
            'btn-success'
        );

        $toolbar = $app->getDocument()->getToolbar('toolbar');
        $dropdown = $toolbar->dropdownButton('storage-status-group');
        $dropdown->text('Actions');
        $dropdown->toggleSplit(false);
        $dropdown->icon('fa fa-ellipsis-h');
        $dropdown->buttonClass('btn btn-action');
        $dropdown->listCheck(true);

        $childToolbar = $dropdown->getChildToolbar();
        $childToolbar->publish('storage.publish')->icon('fa-solid fa-check text-success')->listCheck(true);
        $childToolbar->unpublish('storage.unpublish')->icon('fa-solid fa-circle-xmark text-danger')->listCheck(true);

        $id = (int) ($this->item->id ?? 0);
        $isExternalTable = ((int) ($this->item->bytable ?? 0) === 1);

        if ($id > 0 && !$isExternalTable) {
            ToolbarHelper::custom('datatable.sync', 'refresh', '', Text::_('COM_CONTENTBUILDER_NG_DATATABLE_SYNC'), false);

            $syncTip = json_encode(Text::_('COM_CONTENTBUILDER_NG_DATATABLE_SYNC_TIP'), JSON_UNESCAPED_UNICODE);

            $wa->addInlineScript(
                "(function () {
                    function getButton(task) {
                        return document.querySelector('[data-task=\"' + task + '\"]')
                            || document.querySelector('[onclick*=\"' + task + '\"]');
                    }

                    function applyTooltip(task, message) {
                        var button = getButton(task);
                        if (!button || !message) {
                            return;
                        }
                        button.setAttribute('title', message);
                        button.setAttribute('data-bs-title', message);
                        button.setAttribute('data-bs-toggle', 'tooltip');
                        button.setAttribute('data-bs-placement', 'bottom');

                        if (window.bootstrap && window.bootstrap.Tooltip) {
                            window.bootstrap.Tooltip.getOrCreateInstance(button);
                        }
                    }

                    function init() {
                        applyTooltip('datatable.sync', " . $syncTip . ");
                    }

                    if (document.readyState === 'loading') {
                        document.addEventListener('DOMContentLoaded', init, { once: true });
                    } else {
                        init();
                    }
                }());"
            );
        }
        
        ToolbarHelper::deleteList(
            Text::_('COM_CONTENTBUILDER_NG_DELETE_FIELDS_CONFIRM'),
            'storage.listDelete',
            Text::_('COM_CONTENTBUILDER_NG_DELETE_FIELDS')
        );

        ToolbarHelper::cancel('storage.cancel', $isNew ? 'JTOOLBAR_CANCEL' : 'JTOOLBAR_CLOSE');
        ToolbarHelper::help(
            'COM_CONTENTBUILDER_NG_HELP_STORAGES_TITLE',
            false,
            Uri::base() . 'index.php?option=com_contentbuilder_ng&view=storage&layout=help&tmpl=component'
        );

        parent::display($tpl);
    }

    private function getStorageRecordsCount(object $item): ?int
    {
        $name = trim((string) ($item->name ?? ''));

        if ($name === '') {
            return null;
        }

        $isExternalTable = ((int) ($item->bytable ?? 0) === 1);
        $tableName = $isExternalTable ? $name : ('#__' . $name);

        try {
            $db = Factory::getContainer()->get(DatabaseInterface::class);
            $query = $db->getQuery(true)
                ->select('COUNT(1)')
                ->from($db->quoteName($tableName));

            $db->setQuery($query);

            return (int) $db->loadResult();
        } catch (\Throwable $e) {
            return null;
        }
    }
}
