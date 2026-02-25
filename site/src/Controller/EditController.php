<?php
/**
 * @package     ContentBuilder NG
 * @author      Markus Bopp / XDA+GIL
 * @link        https://breezingforms-ng.vcmb.fr
 * @copyright   Copyright (C) 2026 by XDA+GIL
 * @license     GNU/GPL
 */

namespace CB\Component\Contentbuilder_ng\Site\Controller;

// No direct access
\defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\Router\Route;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Application\CMSApplicationInterface;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\Input\Input;
use CB\Component\Contentbuilder_ng\Administrator\Helper\ContentbuilderLegacyHelper;

class EditController extends BaseController
{
    private bool $frontend;

    private function applyPreviewContextForAction(): bool
    {
        $formId = (int) $this->input->getInt('id', 0);
        $isAdminPreview = $this->isValidAdminPreviewRequest($formId);
        $this->input->set('cb_preview_ok', $isAdminPreview ? 1 : 0);
        Factory::getApplication()->input->set('cb_preview_ok', $isAdminPreview ? 1 : 0);
        return $isAdminPreview;
    }

    public function __construct(
        $config,
        MVCFactoryInterface $factory,
        CMSApplicationInterface $app,
        Input $input
    ) {
        // IMPORTANT : on transmet factory/app/input à BaseController
        parent::__construct($config, $factory, $app, $input);

        $this->frontend = Factory::getApplication()->isClient('site');
       
        Factory::getApplication()->input->set('cbIsNew', 0);

        if (Factory::getApplication()->input->getCmd('task', '') == 'delete' || Factory::getApplication()->input->getCmd('task', '') == 'publish') {
            $items = Factory::getApplication()->input->get('cid', [], 'array');
            ContentbuilderLegacyHelper::setPermissions(Factory::getApplication()->input->getInt('id', 0), $items, $this->frontend ? '_fe' : '');
        } else {
            if (Factory::getApplication()->input->getCmd('record_id', '')) {
                ContentbuilderLegacyHelper::setPermissions(Factory::getApplication()->input->getInt('id', 0), Factory::getApplication()->input->getCmd('record_id', ''), $this->frontend ? '_fe' : '');
            } else {
                Factory::getApplication()->input->set('cbIsNew', 1);
                ContentbuilderLegacyHelper::setPermissions(Factory::getApplication()->input->getInt('id', 0), 0, $this->frontend ? '_fe' : '');
            }
        }
    }

    /**
     * Method to get a model object, loading it if required.
     *
     * @param   string  $name    The model name. Optional.
     * @param   string  $prefix  The class prefix. Optional.
     * @param   array   $config  Configuration array for model. Optional.
     *
     * @return  \Joomla\CMS\MVC\Model\BaseDatabaseModel|false  Model object on success; otherwise false on failure.
     */
    public function getModel($name = 'Edit', $prefix = 'Site', $config = ['ignore_request' => true])
    {
        return parent::getModel($name, $prefix, $config);
    }

    public function save($apply = false)
    {
        $isAdminPreview = $this->applyPreviewContextForAction();

        if (Factory::getApplication()->isClient('site') && Factory::getApplication()->input->getInt('Itemid', 0)) {
            $menu = Factory::getApplication()->getMenu();
            $item = $menu->getActive();
            if (is_object($item)) {
                Factory::getApplication()->input->set('cb_controller', $item->getParams()->get('cb_controller', null));
                Factory::getApplication()->input->set('cb_category_id', $item->getParams()->get('cb_category_id', null));
            }
        }

        Factory::getApplication()->input->set('cbIsNew', 0);
        Factory::getApplication()->input->set('ContentbuilderHelper::cbinternalCheck', 1);

        $isEdit = (bool) Factory::getApplication()->input->getCmd('record_id', '');
        if (!$isEdit) {
            Factory::getApplication()->input->set('cbIsNew', 1);
        }

        if (!$isAdminPreview) {
            if ($isEdit) {
                ContentbuilderLegacyHelper::checkPermissions('edit', Text::_('COM_CONTENTBUILDER_NG_PERMISSIONS_EDIT_NOT_ALLOWED'), $this->frontend ? '_fe' : '');
            } else {
                ContentbuilderLegacyHelper::checkPermissions('new', Text::_('COM_CONTENTBUILDER_NG_PERMISSIONS_NEW_NOT_ALLOWED'), $this->frontend ? '_fe' : '');
            }
        }

        $model = $this->getModel('Edit', 'Site', ['ignore_request' => true])
            ?: $this->getModel('Edit', 'Contentbuilder_ng', ['ignore_request' => true]);

        if (!$model) {
            throw new \RuntimeException('EditModel not found');
        }
        $id = $model->store();

        $submission_failed = Factory::getApplication()->input->getBool('cb_submission_failed', false);
        $cb_submit_msg = Factory::getApplication()->input->set('cb_submit_msg', '');

        $type = 'message';
        if ($id && !$submission_failed) {

            $msg = Text::_('COM_CONTENTBUILDER_NG_SAVED');
            $return = Factory::getApplication()->input->get('return', '', 'string');
            if ($return) {
                $decodedReturn = base64_decode($return, true);
                if ($decodedReturn !== false && Uri::isInternal($decodedReturn)) {
                    Factory::getApplication()->enqueueMessage($msg, 'warning');
                    Factory::getApplication()->redirect($decodedReturn);
                }
            }

        } else {
            $apply = true; // forcing to stay in form on errors
            $type = 'error';
        }

        if ($isAdminPreview) {
            // In admin preview we keep users on the form page.
            $apply = true;
        }

        $app = Factory::getApplication();
        $previewQuery = $this->buildPreviewQuery();
        $listQuery = $this->buildListQuery();

        if (Factory::getApplication()->input->getString('cb_controller', '') == 'edit') {
            $link = Route::_('index.php?option=com_contentbuilder_ng&title=' . Factory::getApplication()->input->get('title', '', 'string') . (Factory::getApplication()->input->get('tmpl', '', 'string') != '' ? '&tmpl=' . Factory::getApplication()->input->get('tmpl', '', 'string') : '') . (Factory::getApplication()->input->get('layout', '', 'string') != '' ? '&layout=' . Factory::getApplication()->input->get('layout', '', 'string') : '') . '&task=edit.display&return=' . Factory::getApplication()->input->get('return', '', 'string') . '&Itemid=' . Factory::getApplication()->input->getInt('Itemid', 0) . $previewQuery, false);
        } else if ($apply) {
            $link = Route::_('index.php?option=com_contentbuilder_ng&title=' . Factory::getApplication()->input->get('title', '', 'string') . (Factory::getApplication()->input->get('tmpl', '', 'string') != '' ? '&tmpl=' . Factory::getApplication()->input->get('tmpl', '', 'string') : '') . (Factory::getApplication()->input->get('layout', '', 'string') != '' ? '&layout=' . Factory::getApplication()->input->get('layout', '', 'string') : '') . '&task=edit.display&return=' . Factory::getApplication()->input->get('return', '', 'string') . '&backtolist=' . Factory::getApplication()->input->getInt('backtolist', 0) . '&id=' . Factory::getApplication()->input->getInt('id', 0) . '&record_id=' . $id . '&Itemid=' . Factory::getApplication()->input->getInt('Itemid', 0) . ($listQuery !== '' ? '&' . $listQuery : '') . $previewQuery, false);
        } else {
            $link = Route::_('index.php?option=com_contentbuilder_ng&title=' . Factory::getApplication()->input->get('title', '', 'string') . (Factory::getApplication()->input->get('tmpl', '', 'string') != '' ? '&tmpl=' . Factory::getApplication()->input->get('tmpl', '', 'string') : '') . (Factory::getApplication()->input->get('layout', '', 'string') != '' ? '&layout=' . Factory::getApplication()->input->get('layout', '', 'string') : '') . '&task=list.display&id=' . Factory::getApplication()->input->getInt('id', 0) . ($listQuery !== '' ? '&' . $listQuery : '') . '&Itemid=' . Factory::getApplication()->input->getInt('Itemid', 0), false);
        }
        $this->setRedirect($link, $msg, $type);
    }

    public function apply()
    {
        $this->save(true);
    }

    public function delete()
    {
        $isAdminPreview = $this->applyPreviewContextForAction();
        if (!$isAdminPreview) {
            ContentbuilderLegacyHelper::checkPermissions('delete', Text::_('COM_CONTENTBUILDER_NG_PERMISSIONS_DELETE_NOT_ALLOWED'), $this->frontend ? '_fe' : '');
        }

        $selectedItems = array_values(
            array_filter(
                array_map('intval', (array) $this->input->get('cid', [], 'array')),
                static fn(int $id): bool => $id > 0
            )
        );

        if ($selectedItems === []) {
            $listQuery = $this->buildListQuery();
            $previewQuery = $this->buildPreviewQuery();
            $link = Route::_(
                'index.php?option=com_contentbuilder_ng&task=list.display&backtolist=1&id='
                . Factory::getApplication()->input->getInt('id', 0)
                . (Factory::getApplication()->input->get('tmpl', '', 'string') != '' ? '&tmpl=' . Factory::getApplication()->input->get('tmpl', '', 'string') : '')
                . (Factory::getApplication()->input->get('layout', '', 'string') != '' ? '&layout=' . Factory::getApplication()->input->get('layout', '', 'string') : '')
                . '&record_id='
                . ($listQuery !== '' ? '&' . $listQuery : '')
                . $previewQuery
                . '&Itemid=' . Factory::getApplication()->input->getInt('Itemid', 0),
                false
            );
            $this->setRedirect($link, Text::_('JERROR_NO_ITEMS_SELECTED'), 'warning');

            return;
        }

        $model = $this->getModel('Edit', 'Site', ['ignore_request' => true])
            ?: $this->getModel('Edit', 'Contentbuilder_ng', ['ignore_request' => true]);
        if (!$model) {
            throw new \RuntimeException('EditModel not found');
        }
        $ok = true;
        try {
            // Legacy model may not return a strict boolean; treat "no exception" as success.
            $model->delete();
        } catch (\Throwable $e) {
            $ok = false;
            $this->app->enqueueMessage($e->getMessage(), 'warning');
        }
        if ($ok) {
            $deletedCount = count($selectedItems);
            if ($deletedCount > 1) {
                $msg = Text::plural('JLIB_APPLICATION_N_ITEMS_DELETED', $deletedCount);
                if (
                    $msg === 'JLIB_APPLICATION_N_ITEMS_DELETED'
                    || str_starts_with($msg, 'JLIB_APPLICATION_N_ITEMS_DELETED_')
                ) {
                    $msg = Text::_('COM_CONTENTBUILDER_NG_ENTRIES_DELETED') . ' (' . $deletedCount . ')';
                }
            } else {
                $msg = Text::_('COM_CONTENTBUILDER_NG_ENTRIES_DELETED');
            }
        } else {
            $msg = Text::_('COM_CONTENTBUILDER_NG_ERROR');
        }
        $type = $ok ? 'message' : 'warning';

        // Clear record context to avoid redirects back to details/edit for a deleted record.
        $this->input->set('record_id', 0);
        Factory::getApplication()->input->set('record_id', 0);

        $listQuery = $this->buildListQuery();
        $previewQuery = $this->buildPreviewQuery();
        $link = Route::_(
            'index.php?option=com_contentbuilder_ng&task=list.display&backtolist=1&id='
            . Factory::getApplication()->input->getInt('id', 0)
            . (Factory::getApplication()->input->get('tmpl', '', 'string') != '' ? '&tmpl=' . Factory::getApplication()->input->get('tmpl', '', 'string') : '')
            . (Factory::getApplication()->input->get('layout', '', 'string') != '' ? '&layout=' . Factory::getApplication()->input->get('layout', '', 'string') : '')
            . '&record_id='
            . ($listQuery !== '' ? '&' . $listQuery : '')
            . $previewQuery
            . '&Itemid=' . Factory::getApplication()->input->getInt('Itemid', 0),
            false
        );

        $this->setRedirect($link, $msg, $type);
    }

    public function state()
    {
        $isAdminPreview = $this->applyPreviewContextForAction();
        if (!$isAdminPreview) {
            ContentbuilderLegacyHelper::checkPermissions('state', Text::_('COM_CONTENTBUILDER_NG_PERMISSIONS_STATE_CHANGE_NOT_ALLOWED'), $this->frontend ? '_fe' : '');
        }

        $model = $this->getModel('Edit', 'Site', ['ignore_request' => true])
            ?: $this->getModel('Edit', 'Contentbuilder_ng', ['ignore_request' => true]);
        if (!$model) {
            throw new \RuntimeException('EditModel not found');
        }
        $model->change_list_states();
        $msg = Text::_('COM_CONTENTBUILDER_NG_STATES_CHANGED');
        $listQuery = $this->buildListQuery();
        $link = Route::_('index.php?option=com_contentbuilder_ng&task=list.display&id=' . Factory::getApplication()->input->getInt('id', 0) . (Factory::getApplication()->input->get('tmpl', '', 'string') != '' ? '&tmpl=' . Factory::getApplication()->input->get('tmpl', '', 'string') : '') . (Factory::getApplication()->input->get('layout', '', 'string') != '' ? '&layout=' . Factory::getApplication()->input->get('layout', '', 'string') : '') . ($listQuery !== '' ? '&' . $listQuery : '') . '&Itemid=' . Factory::getApplication()->input->getInt('Itemid', 0), false);
        $this->setRedirect($link, $msg, 'message');
    }

    public function publish()
    {
        $isAdminPreview = $this->applyPreviewContextForAction();
        if (!$isAdminPreview) {
            ContentbuilderLegacyHelper::checkPermissions('publish', Text::_('COM_CONTENTBUILDER_NG_PERMISSIONS_PUBLISHING_NOT_ALLOWED'), $this->frontend ? '_fe' : '');
        }

        $model = $this->getModel('Edit', 'Site');
        if (!$model) {
            $model = $this->getModel('Edit', 'Contentbuilder_ng');
        }
        if (!$model) {
            throw new \RuntimeException('EditModel introuvable');
        }
        if (method_exists($model, 'setIds')) {
            $model->setIds(Factory::getApplication()->input->getInt('id', 0), Factory::getApplication()->input->getCmd('record_id', 0));
        }
        $model->change_list_publish();
        if (Factory::getApplication()->input->getInt('list_publish', 0)) {
            $msg = Text::_('COM_CONTENTBUILDER_NG_PUBLISHED');
        } else {
            $msg = Text::_('COM_CONTENTBUILDER_NG_UNPUBLISHED');
        }
        $listQuery = $this->buildListQuery();
        $previewQuery = $this->buildPreviewQuery();
        $link = Route::_('index.php?option=com_contentbuilder_ng&task=list.display&id=' . Factory::getApplication()->input->getInt('id', 0) . ($listQuery !== '' ? '&' . $listQuery : '') . (Factory::getApplication()->input->get('tmpl', '', 'string') != '' ? '&tmpl=' . Factory::getApplication()->input->get('tmpl', '', 'string') : '') . (Factory::getApplication()->input->get('layout', '', 'string') != '' ? '&layout=' . Factory::getApplication()->input->get('layout', '', 'string') : '') . $previewQuery . '&Itemid=' . Factory::getApplication()->input->getInt('Itemid', 0), false);
        $this->setRedirect($link, $msg, 'message');
    }

    public function language()
    {
        $isAdminPreview = $this->applyPreviewContextForAction();
        if (!$isAdminPreview) {
            ContentbuilderLegacyHelper::checkPermissions('language', Text::_('COM_CONTENTBUILDER_NG_PERMISSIONS_CHANGE_LANGUAGE_NOT_ALLOWED'), $this->frontend ? '_fe' : '');
        }

        $model = $this->getModel('Edit', 'Site', ['ignore_request' => true])
            ?: $this->getModel('Edit', 'Contentbuilder_ng', ['ignore_request' => true]);
        if (!$model) {
            throw new \RuntimeException('EditModel not found');
        }
        $model->change_list_language();
        $msg = Text::_('COM_CONTENTBUILDER_NG_LANGUAGE_CHANGED');
        $listQuery = $this->buildListQuery();
        $link = Route::_('index.php?option=com_contentbuilder_ng&task=list.display&id=' . Factory::getApplication()->input->getInt('id', 0) . ($listQuery !== '' ? '&' . $listQuery : '') . (Factory::getApplication()->input->get('tmpl', '', 'string') != '' ? '&tmpl=' . Factory::getApplication()->input->get('tmpl', '', 'string') : '') . (Factory::getApplication()->input->get('layout', '', 'string') != '' ? '&layout=' . Factory::getApplication()->input->get('layout', '', 'string') : '') . '&Itemid=' . Factory::getApplication()->input->getInt('Itemid', 0), false);
        $this->setRedirect($link, $msg, 'message');
    }

    private function buildListQuery(): string
    {
        $state = $this->resolveListState();

        return http_build_query(['list' => [
            'limit' => $state['limit'],
            'start' => $state['start'],
            'ordering' => $state['ordering'],
            'direction' => $state['direction'],
        ]]);
    }

    private function resolveListState(): array
    {
        $app = Factory::getApplication();
        $option = 'com_contentbuilder_ng';
        $list = (array) $this->input->get('list', [], 'array');
        $stateKeyPrefix = $this->getPaginationStateKeyPrefix();
        $limitKey = $stateKeyPrefix . '.limit';
        $startKey = $stateKeyPrefix . '.start';

        $limit = isset($list['limit']) ? $app->input->getInt('list[limit]', 0) : 0;
        if ($limit === 0) {
            $limit = (int) $app->getUserState($limitKey, 0);
        }
        if ($limit === 0) {
            $limit = (int) $app->get('list_limit');
        }

        if (array_key_exists('start', $list)) {
            $start = max(0, $app->input->getInt('list[start]', 0));
        } else {
            $start = (int) $app->getUserState($startKey, 0);
        }

        $ordering = isset($list['ordering']) ? $app->input->getCmd('list[ordering]', '') : '';
        if ($ordering === '') {
            $ordering = (string) $app->getUserState($option . 'formsd_filter_order', '');
        }

        $direction = isset($list['direction']) ? $app->input->getCmd('list[direction]', '') : '';
        if ($direction === '') {
            $direction = (string) $app->getUserState($option . 'formsd_filter_order_Dir', '');
        }

        return [
            'limit' => (int) $limit,
            'start' => (int) $start,
            'ordering' => (string) $ordering,
            'direction' => (string) $direction,
        ];
    }

    private function getPaginationStateKeyPrefix(): string
    {
        $app = Factory::getApplication();
        $option = 'com_contentbuilder_ng';

        $formId = (int) $this->input->getInt('id', 0);
        if ($formId < 1) {
            $menu = $app->getMenu()->getActive();
            if ($menu) {
                $formId = (int) $menu->getParams()->get('form_id', 0);
            }
        }

        $layout = (string) $this->input->getCmd('layout', 'default');
        if ($layout === '') {
            $layout = 'default';
        }

        $itemId = (int) $this->input->getInt('Itemid', 0);

        return $option . '.liststate.' . $formId . '.' . $layout . '.' . $itemId;
    }

    private function buildPreviewQuery(): string
    {
        if (!$this->input->getBool('cb_preview', false)) {
            return '';
        }

        $until = (int) $this->input->getInt('cb_preview_until', 0);
        $sig = trim((string) $this->input->getString('cb_preview_sig', ''));
        if ($until <= 0 || $sig === '') {
            return '';
        }

        $actorId = (int) $this->input->getInt('cb_preview_actor_id', 0);
        $actorName = trim((string) $this->input->getString('cb_preview_actor_name', ''));
        $adminReturn = trim((string) $this->input->getCmd('cb_admin_return', ''));

        return '&cb_preview=1'
            . '&cb_preview_until=' . $until
            . '&cb_preview_actor_id=' . $actorId
            . '&cb_preview_actor_name=' . rawurlencode($actorName)
            . '&cb_preview_sig=' . rawurlencode($sig)
            . ($adminReturn !== '' ? '&cb_admin_return=' . rawurlencode($adminReturn) : '');
    }

    public function display($cachable = false, $urlparams = array())
    {
        $app   = Factory::getApplication();

        // Si tu gardes le suffixe pour compat legacy :
        //$frontend = Factory::getApplication()->isClient('site');
        $suffix = '_fe';

        // 1) d'abord depuis l'URL
        $formId   = $this->input->getInt('id', 0);
        $recordId = $this->input->getInt('record_id', 0);

        // 2) sinon depuis les params du menu actif
        if (!$formId) {
            $menu = $app->getMenu()->getActive();
            if ($menu) {
                $formId = (int) $menu->getParams()->get('form_id', 0);
            }
        }

        // Synchroniser l'input pour les appels legacy encore présents.
        $this->input->set('id', $formId);
        Factory::getApplication()->input->set('id', $formId);
        $this->input->set('view', 'edit');

        if ($recordId) {
            $this->input->set('record_id', $recordId);
            Factory::getApplication()->input->set('record_id', $recordId);
        }

        // Contexte CB correct pour cette page
        Factory::getApplication()->input->set('view', 'list');

        // Permissions
        ContentbuilderLegacyHelper::setPermissions($formId, $recordId, $suffix);

        $isAdminPreview = $this->isValidAdminPreviewRequest($formId);
        $this->input->set('cb_preview_ok', $isAdminPreview ? 1 : 0);
        Factory::getApplication()->input->set('cb_preview_ok', $isAdminPreview ? 1 : 0);
        if (!$isAdminPreview) {
            if (Factory::getApplication()->input->getCmd('record_id', '')) {
                ContentbuilderLegacyHelper::checkPermissions('edit', Text::_('COM_CONTENTBUILDER_NG_PERMISSIONS_EDIT_NOT_ALLOWED'), $this->frontend ? '_fe' : '');
            } else {
                ContentbuilderLegacyHelper::checkPermissions('new', Text::_('COM_CONTENTBUILDER_NG_PERMISSIONS_NEW_NOT_ALLOWED'), $this->frontend ? '_fe' : '');
            }
        }

        Factory::getApplication()->input->set('tmpl', Factory::getApplication()->input->getWord('tmpl', null));
        Factory::getApplication()->input->set('layout', Factory::getApplication()->input->getWord('layout', null) == 'latest' ? null : Factory::getApplication()->input->getWord('layout', null));
        Factory::getApplication()->input->set('view', 'Edit');

        parent::display();
    }

    /**
     * Validates a short-lived preview signature generated in admin toolbar.
     */
    private function isValidAdminPreviewRequest(int $formId): bool
    {
        if ($formId < 1 || !$this->input->getBool('cb_preview', false)) {
            return false;
        }

        $until = (int) $this->input->getInt('cb_preview_until', 0);
        $sig   = (string) $this->input->getString('cb_preview_sig', '');
        $actorId = (int) $this->input->getInt('cb_preview_actor_id', 0);
        $actorName = trim((string) $this->input->getString('cb_preview_actor_name', ''));

        if ($until < time() || $sig === '') {
            return false;
        }

        $secret = (string) Factory::getApplication()->get('secret');
        if ($secret === '') {
            return false;
        }

        $payload  = $formId . '|' . $until;
        $expected = hash_hmac('sha256', $payload, $secret);
        $actorPayload = $payload . '|' . $actorId . '|' . $actorName;
        $actorExpected = hash_hmac('sha256', $actorPayload, $secret);

        if (($actorId > 0 || $actorName !== '') && hash_equals($actorExpected, $sig)) {
            $this->input->set('cb_preview_actor_id', $actorId);
            $this->input->set('cb_preview_actor_name', $actorName);
            Factory::getApplication()->input->set('cb_preview_actor_id', $actorId);
            Factory::getApplication()->input->set('cb_preview_actor_name', $actorName);
            return true;
        }

        if (hash_equals($expected, $sig)) {
            // Legacy preview links (without actor) stay valid, but without actor propagation.
            $this->input->set('cb_preview_actor_id', 0);
            $this->input->set('cb_preview_actor_name', '');
            Factory::getApplication()->input->set('cb_preview_actor_id', 0);
            Factory::getApplication()->input->set('cb_preview_actor_name', '');
            return true;
        }

        return false;
    }
}
