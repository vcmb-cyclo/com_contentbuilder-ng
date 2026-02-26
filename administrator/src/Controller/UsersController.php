<?php
/**
 * @package     ContentBuilder NG
 * @author      Markus Bopp / XDA+GIL
 * @link        https://breezingforms-ng.vcmb.fr
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
*/

namespace CB\Component\Contentbuilderng\Administrator\Controller;

// No direct access
defined( '_JEXEC' ) or die( 'Restricted access' );

use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Factory;
use Joomla\CMS\Response\JsonResponse;
use Joomla\CMS\Application\CMSApplicationInterface;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\Input\Input;
use CB\Component\Contentbuilderng\Administrator\Model\UserModel;
use CB\Component\Contentbuilderng\Administrator\Model\UsersModel;

class UsersController extends BaseController
{
    private function getUserModelForListActions(): UserModel
    {
        $model = $this->getModel('User');

        if (!$model instanceof UserModel) {
            throw new \RuntimeException('User model not found');
        }

        return $model;
    }

    private function getUsersModelForPublishActions(): UsersModel
    {
        $model = $this->getModel('users');

        if (!$model instanceof UsersModel) {
            throw new \RuntimeException('Users model not found');
        }

        return $model;
    }

    private function getUserModelForSave(): UserModel
    {
        $model = $this->getModel('User', 'Administrator', ['ignore_request' => true])
            ?: $this->getModel('User', 'Contentbuilderng', ['ignore_request' => true]);

        if (!$model instanceof UserModel) {
            throw new \RuntimeException('UserModel not found');
        }

        return $model;
    }

    public function __construct(
        $config,
        MVCFactoryInterface $factory,
        CMSApplicationInterface $app,
        Input $input
    ) {
        // IMPORTANT : on transmet factory/app/input à BaseController
        parent::__construct($config, $factory, $app, $input);
        
        // Register Extra tasks
        $this->registerTask( 'add', 'edit' );
    }
    
    public function verified_view() {
        try {
            $model = $this->getUserModelForListActions();
            $model->setListVerifiedView();

            if ($this->isAjaxCall()) {
                $this->respondAjax(true, Text::_('COM_CONTENTBUILDERNG_SAVED'));
                return;
            }
        } catch (\Throwable $e) {
            if ($this->isAjaxCall()) {
                $this->respondAjax(false, $e->getMessage());
                return;
            }
            $this->setMessage($e->getMessage(), 'warning');
        }

        $this->renderUsersList();
    }
    
    public function not_verified_view() {
        try {
            $model = $this->getUserModelForListActions();
            $model->setListNotVerifiedView();

            if ($this->isAjaxCall()) {
                $this->respondAjax(true, Text::_('COM_CONTENTBUILDERNG_SAVED'));
                return;
            }
        } catch (\Throwable $e) {
            if ($this->isAjaxCall()) {
                $this->respondAjax(false, $e->getMessage());
                return;
            }
            $this->setMessage($e->getMessage(), 'warning');
        }

        $this->renderUsersList();
    }
    
    public function verified_new() {
        try {
            $model = $this->getUserModelForListActions();
            $model->setListVerifiedNew();

            if ($this->isAjaxCall()) {
                $this->respondAjax(true, Text::_('COM_CONTENTBUILDERNG_SAVED'));
                return;
            }
        } catch (\Throwable $e) {
            if ($this->isAjaxCall()) {
                $this->respondAjax(false, $e->getMessage());
                return;
            }
            $this->setMessage($e->getMessage(), 'warning');
        }

        $this->renderUsersList();
    }
    
    public function not_verified_new() {
        try {
            $model = $this->getUserModelForListActions();
            $model->setListNotVerifiedNew();

            if ($this->isAjaxCall()) {
                $this->respondAjax(true, Text::_('COM_CONTENTBUILDERNG_SAVED'));
                return;
            }
        } catch (\Throwable $e) {
            if ($this->isAjaxCall()) {
                $this->respondAjax(false, $e->getMessage());
                return;
            }
            $this->setMessage($e->getMessage(), 'warning');
        }

        $this->renderUsersList();
    }
    
    public function verified_edit() {
        try {
            $model = $this->getUserModelForListActions();
            $model->setListVerifiedEdit();

            if ($this->isAjaxCall()) {
                $this->respondAjax(true, Text::_('COM_CONTENTBUILDERNG_SAVED'));
                return;
            }
        } catch (\Throwable $e) {
            if ($this->isAjaxCall()) {
                $this->respondAjax(false, $e->getMessage());
                return;
            }
            $this->setMessage($e->getMessage(), 'warning');
        }

        $this->renderUsersList();
    }
    
    public function not_verified_edit() {
        try {
            $model = $this->getUserModelForListActions();
            $model->setListNotVerifiedEdit();

            if ($this->isAjaxCall()) {
                $this->respondAjax(true, Text::_('COM_CONTENTBUILDERNG_SAVED'));
                return;
            }
        } catch (\Throwable $e) {
            if ($this->isAjaxCall()) {
                $this->respondAjax(false, $e->getMessage());
                return;
            }
            $this->setMessage($e->getMessage(), 'warning');
        }

        $this->renderUsersList();
    }
    
    public function edit()
    {
        Factory::getApplication()->input->set( 'view', 'User' );
        Factory::getApplication()->input->set( 'layout', 'default'  );
        Factory::getApplication()->input->set( 'hidemainmenu', 1 );
        Factory::getApplication()->input->set( 'filter_order', 'ordering' );
        Factory::getApplication()->input->set( 'filter_order_Dir', 'asc' );
        parent::display();
    }

    public function apply()
    {
        $this->save(true);
    }

    public function publish() {
        try {
            $model = $this->getUsersModelForPublishActions();
            $model->setPublished();

            if ($this->isAjaxCall()) {
                $this->respondAjax(true, Text::_('COM_CONTENTBUILDERNG_PUBLISHED'));
                return;
            }
        } catch (\Throwable $e) {
            if ($this->isAjaxCall()) {
                $this->respondAjax(false, $e->getMessage());
                return;
            }
            $this->setMessage($e->getMessage(), 'warning');
        }

        $this->setRedirect( Route::_('index.php?option=com_contentbuilderng&view=users&form_id='.Factory::getApplication()->input->getInt('form_id',0).'&tmpl='.Factory::getApplication()->input->getCmd('tmpl','').'&limitstart='.Factory::getApplication()->input->getInt('limitstart'), false), Text::_('COM_CONTENTBUILDERNG_PUBLISHED') );
    }
    
    public function unpublish() {
        try {
            $model = $this->getUsersModelForPublishActions();
            $model->setUnpublished();

            if ($this->isAjaxCall()) {
                $this->respondAjax(true, Text::_('COM_CONTENTBUILDERNG_UNPUBLISHED'));
                return;
            }
        } catch (\Throwable $e) {
            if ($this->isAjaxCall()) {
                $this->respondAjax(false, $e->getMessage());
                return;
            }
            $this->setMessage($e->getMessage(), 'warning');
        }

        $this->setRedirect( Route::_('index.php?option=com_contentbuilderng&view=users&form_id='.Factory::getApplication()->input->getInt('form_id',0).'&tmpl='.Factory::getApplication()->input->getCmd('tmpl','').'&limitstart='.Factory::getApplication()->input->getInt('limitstart'), false), Text::_('COM_CONTENTBUILDERNG_UNPUBLISHED') );
    }
    
    public function save($keep_task = false)
    {
        $model = $this->getUserModelForSave();
        $id = $model->store();
        
        if ($id) {
            $msg = Text::_( 'COM_CONTENTBUILDERNG_SAVED' );
        } else {
            $msg = Text::_( 'COM_CONTENTBUILDERNG_ERROR' );
        }

        $limit = 0;
        $additionalParams = '';
        if($keep_task){
            if($id){
                $additionalParams = '&task=User.edit&joomla_userid='.$id;
                $limit = Factory::getApplication()->input->getInt('limitstart');
            }
        }

        // Check the table in so it can be edited.... we are done with it anyway
        $link = 'index.php?option=com_contentbuilderng&view=users&form_id='.Factory::getApplication()->input->getInt('form_id',0).'&tmpl='.Factory::getApplication()->input->getCmd('tmpl','').'&limitstart='.$limit.$additionalParams;
        $this->setRedirect(Route::_($link, false), $msg);
    }

    public function cancel()
    {
        $msg = Text::_( 'COM_CONTENTBUILDERNG_CANCELLED' );
        $this->setRedirect( Route::_('index.php?option=com_contentbuilderng&view=users&form_id='.Factory::getApplication()->input->getInt('form_id',0).'&tmpl='.Factory::getApplication()->input->getCmd('tmpl','').'&limitstart=0', false), $msg );
    }

    public function display($cachable = false, $urlparams = array())
    {
        $this->renderUsersList();
    }

    private function renderUsersList(): void
    {
        Factory::getApplication()->input->set('tmpl', Factory::getApplication()->input->getWord('tmpl',null));
        Factory::getApplication()->input->set('layout', Factory::getApplication()->input->getWord('layout',null));
        Factory::getApplication()->input->set('view', 'users');

        parent::display();
    }

    private function isAjaxCall(): bool
    {
        return (bool) $this->input->getInt('cb_ajax', 0);
    }

    private function respondAjax(bool $success, string $message = ''): void
    {
        echo new JsonResponse(['ok' => $success], $message, !$success);
        Factory::getApplication()->close();
    }
}
