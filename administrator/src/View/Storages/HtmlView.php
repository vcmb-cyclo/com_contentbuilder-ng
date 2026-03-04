<?php
/**
 * @package     ContentBuilder NG
 * @author      Markus Bopp / XDA+GIL
 * @copyright   Copyright © 2026 by XDA+GIL
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace CB\Component\Contentbuilderng\Administrator\View\Storages;

\defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Language\Text;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\CMS\Document\HtmlDocument;
use Joomla\CMS\Uri\Uri;
use CB\Component\Contentbuilderng\Administrator\Model\StoragesModel;
use CB\Component\Contentbuilderng\Administrator\View\Contentbuilderng\HtmlView as BaseHtmlView;

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

        /** @var StoragesModel $model */
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
        ToolbarHelper::title(Text::_('COM_CONTENTBUILDERNG') .' :: ' . Text::_('COM_CONTENTBUILDERNG_STORAGES'), 'logo_left');

        ToolbarHelper::addNew('storage.add');
        ToolbarHelper::editList('storage.edit');
        ToolbarHelper::deleteList('JGLOBAL_CONFIRM_DELETE', 'storage.delete');
        /** @var CMSApplication $app */
        $app = Factory::getApplication();
        /** @var HtmlDocument $document */
        $document = $app->getDocument();
        $toolbar = $document->getToolbar('toolbar');

        $statusDropdown = $toolbar->dropdownButton('storages-status-group');
        $statusDropdown->text('Actions');
        $statusDropdown->toggleSplit(false);
        $statusDropdown->icon('fa fa-ellipsis-h');
        $statusDropdown->buttonClass('btn btn-action');
        $statusDropdown->listCheck(true);

        $statusChildToolbar = $statusDropdown->getChildToolbar();
        $statusChildToolbar->publish('storages.publish')->icon('fa-solid fa-check text-success')->listCheck(true);
        $statusChildToolbar->unpublish('storages.unpublish')->icon('fa-solid fa-circle-xmark text-danger')->listCheck(true);

        ToolbarHelper::preferences('com_contentbuilderng');
        ToolbarHelper::help(
            'COM_CONTENTBUILDERNG_HELP_STORAGES_TITLE',
            false,
            Uri::base() . 'index.php?option=com_contentbuilderng&view=storages&layout=help&tmpl=component'
        );
    }

    /**
     * Ajoute l'icône personnalisée pour le titre de la barre d'outils
     */
    protected function addToolbarIcon()
    {
         // 1️⃣ Récupération du WebAssetManager
        /** @var CMSApplication $app */
        $app = Factory::getApplication();
        $document = $app->getDocument();
        $wa = $document->getWebAssetManager();

         // Icon addition.
        $wa->addInlineStyle(
            '.icon-logo_left{
                background-image:url(' . Uri::root(true) . '/media/com_contentbuilderng/images/logo_left.png);
                background-size:contain;
                background-repeat:no-repeat;
                background-position:center;
                display:inline-block;
                width:48px;
                height:48px;
                vertical-align:middle;
            }'
        );

        $wa->getRegistry()->addExtensionRegistryFile('com_contentbuilderng');
        $wa->useStyle('com_contentbuilderng.admin-toolbar'); // A déclarer dans joomla.asset.json
    }
}
