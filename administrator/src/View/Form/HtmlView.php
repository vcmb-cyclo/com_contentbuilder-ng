<?php

/**
 * @package ContentBuilder
 * @author Markus Bopp / XDA+GIL
 * @link https://breezingforms-ng.vcmb.fr
 * @copyright (C) 2026 by XDA+GIL
 * @license GNU/GPL
 */

namespace CB\Component\Contentbuilderng\Administrator\View\Form;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\CMS\Uri\Uri;
use Joomla\Database\DatabaseInterface;
use Joomla\CMS\Application\CMSApplication;
use CB\Component\Contentbuilderng\Administrator\Helper\PackedDataHelper;
use CB\Component\Contentbuilderng\Administrator\Model\FormModel;
use CB\Component\Contentbuilderng\Administrator\Model\ElementsModel;
use CB\Component\Contentbuilderng\Administrator\Extension\ContentbuilderngComponent;
use CB\Component\Contentbuilderng\Administrator\View\Contentbuilderng\HtmlView as BaseHtmlView;

class HtmlView extends BaseHtmlView
{
    public function display($tpl = null)
    {
        if ($this->getLayout() === 'help') {
            parent::display($tpl);
            return;
        }

        /** @var CMSApplication $app */
        $app = Factory::getApplication();
        $app->input->set('hidemainmenu', true);

        // JS
        /** @var \Joomla\CMS\Document\AdminDocument $document */
        $document = $app->getDocument();
        $wa = $document->getWebAssetManager();
        $wa->getRegistry()->addExtensionRegistryFile('com_contentbuilderng');
        $wa->useStyle('com_contentbuilderng.coloris.css');
        $wa->useScript('com_contentbuilderng.coloris.js');

        $wa->addInlineStyle(
            '.icon-48-logo_icon_cb{background-image:url('
            . Uri::root(true)
            . '/media/com_contentbuilderng/images/logo_icon_cb.png);background-size:contain;background-repeat:no-repeat;}'
        );


        // Formulaire JForm
        /** @var FormModel $model */
        $model = $this->getModel();
        $this->form = $model->getForm();

        // Données (l’item)
        $this->item = $model->getItem();

        // Chargement sécurisé des éléments
        $formId = (int) ($this->item->id ?? $app->input->getInt('id', 0));

        $this->elements = [];
        $this->all_elements = [];
        $this->pagination = null;
        $this->state = null;

        try {
            $formId = (int) ($formId ?? 0);
            if ($formId > 0) {
                /** @var ContentbuilderngComponent $component */
                $component = $app->bootComponent('com_contentbuilderng');
                if (!$component instanceof ContentbuilderngComponent) {
                    throw new \RuntimeException('Composant com_contentbuilderng introuvable (factory)');
                }
                $factory = $component->getMVCFactory();
                /** @var ElementsModel $elementsModel */
                $elementsModel = $factory->createModel('Elements', 'Administrator');

                if (!$elementsModel) {
                    throw new \RuntimeException('Modèle Elements introuvable (factory)');
                }

                // IMPORTANT : fournir le form id au ListModel
                $elementsModel->setFormId($formId);

                // Charge les items
                $this->elements   = $elementsModel->getItems();
                $this->all_elements = $elementsModel->getAllElements($formId) ?? [];
                $this->pagination = $elementsModel->getPagination();
                $this->state      = $elementsModel->getState();
            }
        } catch (\Throwable $e) {
                $app->enqueueMessage(
                    'Erreur lors du chargement des éléments : ' . $e->getMessage(),
                    'warning'
                );
        }

        $isNew = ($formId < 1);
        $text  = $isNew ? Text::_('COM_CONTENTBUILDERNG_NEW') : Text::_('COM_CONTENTBUILDERNG_EDIT');

        ToolbarHelper::title(
            Text::_('COM_CONTENTBUILDERNG') .' :: ' . ($isNew ? Text::_('COM_CONTENTBUILDERNG_FORM') : ($this->item->name ?? '')) .
                ' : <small><small>[ ' . $text . ' ]</small></small>',
            'logo_left'
        );

        ToolbarHelper::saveGroup(
            [
                ['apply', 'form.apply', 'JTOOLBAR_APPLY'],
                ['save', 'form.save', 'JTOOLBAR_SAVE'],
                ['save2new', 'form.save2new', 'JTOOLBAR_SAVE_AND_NEW'],
            ],
            'btn-success'
        );

        /** @var \Joomla\CMS\Toolbar\Toolbar $toolbar */
        /** @var \Joomla\CMS\Toolbar\Toolbar $toolbar */
        $toolbar = $document->getToolbar('toolbar');

        $statusDropdown = $toolbar->dropdownButton('form-status-group');
        $statusDropdown->text('Actions');
        $statusDropdown->toggleSplit(false);
        $statusDropdown->icon('fa fa-ellipsis-h');
        $statusDropdown->buttonClass('btn btn-action');
        $statusDropdown->listCheck(true);

        $statusChildToolbar = $statusDropdown->getChildToolbar();
        $statusChildToolbar->standardButton('list_include')
            ->task('form.list_include')
            ->text('COM_CONTENTBUILDERNG_LIST_INCLUDE')
            ->icon('fa fa-list text-success')
            ->listCheck(true);
        $statusChildToolbar->standardButton('no_list_include')
            ->task('form.no_list_include')
            ->text('COM_CONTENTBUILDERNG_NO_LIST_INCLUDE')
            ->icon('fa fa-list text-danger')
            ->listCheck(true);
        $statusChildToolbar->standardButton('search_include')
            ->task('form.search_include')
            ->text('COM_CONTENTBUILDERNG_SEARCH_INCLUDE')
            ->icon('fa fa-search text-success')
            ->listCheck(true);
        $statusChildToolbar->standardButton('no_search_include')
            ->task('form.no_search_include')
            ->text('COM_CONTENTBUILDERNG_NO_SEARCH_INCLUDE')
            ->icon('fa fa-search text-danger')
            ->listCheck(true);
        $statusChildToolbar->standardButton('linkable')
            ->task('form.linkable')
            ->text('COM_CONTENTBUILDERNG_LINKABLE')
            ->icon('fa fa-link text-success')
            ->listCheck(true);
        $statusChildToolbar->standardButton('not_linkable')
            ->task('form.not_linkable')
            ->text('COM_CONTENTBUILDERNG_NOT_LINKABLE')
            ->icon('fa fa-link text-danger')
            ->listCheck(true);
        $statusChildToolbar->standardButton('editable')
            ->task('form.editable')
            ->text('COM_CONTENTBUILDERNG_EDITABLE')
            ->icon('fa fa-pen text-success')
            ->listCheck(true);
        $statusChildToolbar->standardButton('not_editable')
            ->task('form.not_editable')
            ->text('COM_CONTENTBUILDERNG_NOT_EDITABLE')
            ->icon('fa fa-pen text-danger')
            ->listCheck(true);
        $statusChildToolbar->publish('form.publish')->icon('fa-solid fa-check text-success')->listCheck(true);
        $statusChildToolbar->unpublish('form.unpublish')->icon('fa-solid fa-circle-xmark text-danger')->listCheck(true);

        // Keep Preview aligned with the right-side help button.
        $wa->addInlineStyle('.cb-toolbar-preview{margin-inline-start:auto!important;}');
        $wa->addInlineScript(
            "(function () {
                function getToolbarHost() {
                    return document.getElementById('toolbar')
                        || document.querySelector('joomla-toolbar')
                        || document.querySelector('.toolbar');
                }

                function resolveToolbarButtonHost(node) {
                    if (!node) {
                        return null;
                    }

                    var host = node.closest('joomla-toolbar-button, .toolbar-button, .btn-wrapper');
                    if (host) {
                        return host;
                    }

                    if (typeof node.getRootNode === 'function') {
                        var root = node.getRootNode();
                        if (root && root.host) {
                            return root.host;
                        }
                    }

                    return node.parentElement || null;
                }

                function findHostByHref(fragment) {
                    var selector = 'a[href*=\"' + fragment + '\"],button[href*=\"' + fragment + '\"]';
                    var direct = document.querySelector(selector);
                    if (direct) {
                        return resolveToolbarButtonHost(direct);
                    }

                    var toolbarButtons = document.querySelectorAll('joomla-toolbar-button');
                    for (var i = 0; i < toolbarButtons.length; i++) {
                        var host = toolbarButtons[i];
                        var shadow = host.shadowRoot;
                        if (shadow && shadow.querySelector(selector)) {
                            return host;
                        }
                    }

                    return null;
                }

                function getHelpHost() {
                    var helpById = document.getElementById('toolbar-help')
                        || document.querySelector('[id*=\"toolbar-help\"]');
                    if (helpById) {
                        return resolveToolbarButtonHost(helpById);
                    }

                    var byHref = findHostByHref('layout=help');
                    if (byHref) {
                        return byHref;
                    }

                    var toolbarButtons = document.querySelectorAll('joomla-toolbar-button');
                    for (var i = 0; i < toolbarButtons.length; i++) {
                        var hostId = String(toolbarButtons[i].id || '').toLowerCase();
                        if (hostId.indexOf('help') !== -1) {
                            return toolbarButtons[i];
                        }
                    }

                    return null;
                }

                function getPreviewHost() {
                    var previewById = document.getElementById('toolbar-preview')
                        || document.querySelector('[id*=\"toolbar-preview\"]');
                    if (previewById) {
                        return resolveToolbarButtonHost(previewById);
                    }

                    var byHref = findHostByHref('cb_preview=1') || findHostByHref('cb_preview_sig');
                    if (byHref) {
                        return byHref;
                    }

                    var toolbarButtons = document.querySelectorAll('joomla-toolbar-button');
                    for (var i = 0; i < toolbarButtons.length; i++) {
                        var host = toolbarButtons[i];
                        var hostId = String(host.id || '').toLowerCase();
                        if (hostId.indexOf('preview') !== -1) {
                            return host;
                        }

                        var shadow = host.shadowRoot;
                        if (shadow && shadow.querySelector('.fa-solid fa-eye, .fa-eye')) {
                            return host;
                        }
                    }

                    return null;
                }

                function alignPreviewNearHelp() {
                    var previewHost = getPreviewHost();
                    if (!previewHost) {
                        return;
                    }

                    previewHost.classList.add('cb-toolbar-preview');

                    var helpHost = getHelpHost();
                    if (!helpHost || !helpHost.parentNode || previewHost === helpHost) {
                        return;
                    }

                    if (previewHost.parentNode !== helpHost.parentNode || previewHost.nextElementSibling !== helpHost) {
                        helpHost.parentNode.insertBefore(previewHost, helpHost);
                    }
                }

                function init() {
                    var toolbarHost = getToolbarHost();
                    alignPreviewNearHelp();

                    if (toolbarHost && typeof MutationObserver === 'function') {
                        var observer = new MutationObserver(alignPreviewNearHelp);
                        observer.observe(toolbarHost, { childList: true, subtree: true });
                        window.setTimeout(function () {
                            observer.disconnect();
                        }, 6000);
                    }

                    window.setTimeout(alignPreviewNearHelp, 0);
                    window.setTimeout(alignPreviewNearHelp, 120);
                    window.setTimeout(alignPreviewNearHelp, 400);
                }

                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', init, { once: true });
                } else {
                    init();
                }
            }());"
        );

        ToolbarHelper::cancel('form.cancel', $isNew ? 'JTOOLBAR_CLOSE' : 'JTOOLBAR_CLOSE');

        if ($formId > 0) {
            $previewUntil = time() + 600;
            $previewActorId = (int) ($app->getIdentity()->id ?? 0);
            $previewActorName = trim((string) ($app->getIdentity()->name ?? ''));
            if ($previewActorName === '') {
                $previewActorName = trim((string) ($app->getIdentity()->username ?? ''));
            }
            if ($previewActorName === '') {
                $previewActorName = 'administrator';
            }
            $previewPayload = $formId . '|' . $previewUntil . '|' . $previewActorId . '|' . $previewActorName;
            $previewSig = hash_hmac('sha256', $previewPayload, (string) $app->get('secret'));
            $previewUrl = Uri::root()
                . 'index.php?option=com_contentbuilderng&task=list.display&id='
                . $formId
                . '&cb_preview=1'
                . '&cb_preview_until=' . $previewUntil
                . '&cb_preview_actor_id=' . $previewActorId
                . '&cb_preview_actor_name=' . rawurlencode($previewActorName)
                . '&cb_preview_sig=' . $previewSig;
            $toolbar->appendButton(
                'Link',
                'eye',
                Text::_('COM_CONTENTBUILDERNG_PREVIEW'),
                $previewUrl,
                '_blank'
            );
        }

        ToolbarHelper::help(
            'COM_CONTENTBUILDERNG_HELP_VIEWS_TITLE',
            false,
            Uri::base() . 'index.php?option=com_contentbuilderng&view=form&layout=help&tmpl=component'
        );

        // Compat template / listes
        $this->listOrder = (string) $this->state?->get('list.ordering', 'ordering');
        $this->listDirn  = (string) $this->state?->get('list.direction', 'ASC');

        $lists['order']     = $this->listOrder;
        $lists['order_Dir'] = $this->listDirn;

        // ordering actif seulement si tri par ordering
        $this->ordering = ($this->listOrder === 'ordering');


        // Données additionnelles
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $q = $db->getQuery(true)
            ->select("CONCAT(REPEAT('..', COUNT(parent.id) - 1), node.title) AS " . $db->quoteName('text') . ", node.id AS " . $db->quoteName('value'))
            ->from($db->quoteName('#__usergroups', 'node'))
            ->from($db->quoteName('#__usergroups', 'parent'))
            ->where('node.lft BETWEEN parent.lft AND parent.rgt')
            ->group('node.id')
            ->order('node.lft');

        $db->setQuery($q);
        $this->gmap = $db->loadObjectList() ?? [];


        $config = PackedDataHelper::decodePackedData($this->item->config ?? null, null, true);
        $this->item->config = is_array($config) ? $config : null;

        $this->list_states_action_plugins = $model->getListStatesActionPlugins();
        $this->verification_plugins       = $model->getVerificationPlugins();
        $this->theme_plugins              = $model->getThemePlugins();

        HTMLHelper::_('behavior.keepalive');
        $this->setLayout('edit');
        parent::display($tpl);
    }

}
