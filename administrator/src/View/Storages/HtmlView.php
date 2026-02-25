<?php
/**
 * @package     ContentBuilder NG
 * @author      Markus Bopp / XDA+GIL
 * @copyright   Copyright (C) 2026 by XDA+GIL
 * @license     GNU/GPL
 */

namespace CB\Component\Contentbuilder_ng\Administrator\View\Storages;

\defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Language\Text;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\CMS\Uri\Uri;
use CB\Component\Contentbuilder_ng\Administrator\View\Contentbuilder_ng\HtmlView as BaseHtmlView;

/**
 * Vue Storages pour ContentBuilder
 */
class HtmlView extends BaseHtmlView
{
    protected $items;
    protected $pagination;
    protected $state;
    protected $lists;
    protected $ordering;

    /**
     * Méthode d'affichage de la vue
     *
     * @param   string  $tpl  Nom du template alternatif
     * @return  void
     */
    public function display($tpl = null)
    {
        if ($this->getLayout() === 'help') {
            parent::display($tpl);
            return;
        }

        $model = $this->getModel();

        try {
            // Récupération des données du modèle
            $this->items      = (array) ($model->getItems() ?? []);
            $this->pagination = $model->getPagination();
            $this->state      = $model->getState();
        } catch (\Throwable $e) {
            throw new \RuntimeException($e->getMessage(), (int) $e->getCode(), $e);
        }

        // Préparation des filtres et tris (Joomla standard)
        $this->lists['order_Dir'] = (string) $this->state->get('list.direction', 'ASC');
        $this->lists['order']     = (string) $this->state->get('list.ordering', 'a.ordering');

        // Si tu as un filtre published standard
        $this->lists['state']     = HTMLHelper::_('grid.state', (string) $this->state->get('filter.state', ''));

        // Ton flag ordering (ton template compare à "ordering" mais toi tu utilises souvent "a.ordering")
        $this->ordering = ($this->lists['order'] === 'a.ordering' || $this->lists['order'] === 'ordering');

        // Ajout du CSS personnalisé (méthode propre)
        $this->addToolbarIcon();

        // Barre d'outils
        $this->addToolbar();

        HTMLHelper::_('behavior.keepalive');
        parent::display($tpl);
    }

    /**
     * Ajoute la barre d'outils
     */
    protected function addToolbar()
    {
        ToolbarHelper::title(Text::_('COM_CONTENTBUILDER_NG') .' :: ' . Text::_('COM_CONTENTBUILDER_NG_STORAGES'), 'logo_left');

        ToolbarHelper::addNew('storage.add');
        ToolbarHelper::editList('storage.edit');
        ToolbarHelper::deleteList('JGLOBAL_CONFIRM_DELETE', 'storage.delete');
        $toolbar = Factory::getApplication()->getDocument()->getToolbar('toolbar');

        $statusDropdown = $toolbar->dropdownButton('storages-status-group');
        $statusDropdown->text('Actions');
        $statusDropdown->toggleSplit(false);
        $statusDropdown->icon('fa fa-ellipsis-h');
        $statusDropdown->buttonClass('btn btn-action');
        $statusDropdown->listCheck(true);

        $statusChildToolbar = $statusDropdown->getChildToolbar();
        $statusChildToolbar->publish('storages.publish')->icon('fa-solid fa-check text-success')->listCheck(true);
        $statusChildToolbar->unpublish('storages.unpublish')->icon('fa-solid fa-circle-xmark text-danger')->listCheck(true);

        ToolbarHelper::preferences('com_contentbuilder_ng');
        ToolbarHelper::help(
            'COM_CONTENTBUILDER_NG_HELP_STORAGES_TITLE',
            false,
            Uri::base() . 'index.php?option=com_contentbuilder_ng&view=storages&layout=help&tmpl=component'
        );
    }

    /**
     * Ajoute l'icône personnalisée pour le titre de la barre d'outils
     */
    protected function addToolbarIcon()
    {
         // 1️⃣ Récupération du WebAssetManager
        $document = $this->getDocument();
        $wa = $document->getWebAssetManager();

         // Icon addition.
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

        $wa->getRegistry()->addExtensionRegistryFile('com_contentbuilder_ng');
        $wa->useStyle('com_contentbuilder_ng.admin-toolbar'); // A déclarer dans joomla.asset.json
    }
}
