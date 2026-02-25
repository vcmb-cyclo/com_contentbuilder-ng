<?php
/**
 * @package     ContentBuilder NG
 * @author      Markus Bopp / XDA+GIL
 * @link        https://breezingforms-ng.vcmb.fr
 * @license     GNU/GPL
*/

namespace CB\Component\Contentbuilder_ng\Administrator\Controller;

// No direct access
defined( '_JEXEC' ) or die( 'Restricted access' );

use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Factory;
use Joomla\CMS\Application\CMSApplicationInterface;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\Input\Input;
use CB\Component\Contentbuilder_ng\Administrator\Model\UserModel;
use CB\Component\Contentbuilder_ng\Administrator\Model\UsersModel;

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
            ?: $this->getModel('User', 'Contentbuilder_ng', ['ignore_request' => true]);

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
        $cid = Factory::getApplication()->input->get('cid', [], 'array');

        $model = $this->getUserModelForListActions();
        $model->setListVerifiedView();

        Factory::getApplication()->input->set( 'view', 'users' );
        Factory::getApplication()->input->set( 'layout', 'default'  );
        Factory::getApplication()->input->set( 'hidemainmenu', 1 );
        Factory::getApplication()->input->set( 'filter_order', 'ordering' );
        Factory::getApplication()->input->set( 'filter_order_Dir', 'asc' );
        Factory::getApplication()->input->set( 'limitstart', Factory::getApplication()->input->getInt('limitstart') );

        parent::display();
    }
    
    public function not_verified_view() {
        $cid = Factory::getApplication()->input->get('cid', [], 'array');

        $model = $this->getUserModelForListActions();
        $model->setListNotVerifiedView();

        Factory::getApplication()->input->set( 'view', 'users' );
        Factory::getApplication()->input->set( 'layout', 'default'  );
        Factory::getApplication()->input->set( 'hidemainmenu', 1 );
        Factory::getApplication()->input->set( 'filter_order', 'ordering' );
        Factory::getApplication()->input->set( 'filter_order_Dir', 'asc' );
        Factory::getApplication()->input->set( 'limitstart', Factory::getApplication()->input->getInt('limitstart') );

        parent::display();
    }
    
    public function verified_new() {
        $cid = Factory::getApplication()->input->get('cid', [], 'array');

        $model = $this->getUserModelForListActions();
        $model->setListVerifiedNew();

        Factory::getApplication()->input->set( 'view', 'users' );
        Factory::getApplication()->input->set( 'layout', 'default'  );
        Factory::getApplication()->input->set( 'hidemainmenu', 1 );
        Factory::getApplication()->input->set( 'filter_order', 'ordering' );
        Factory::getApplication()->input->set( 'filter_order_Dir', 'asc' );
        Factory::getApplication()->input->set( 'limitstart', Factory::getApplication()->input->getInt('limitstart') );

        parent::display();
    }
    
    public function not_verified_new() {
        $cid = Factory::getApplication()->input->get('cid', [], 'array');

        $model = $this->getUserModelForListActions();
        $model->setListNotVerifiedNew();

        Factory::getApplication()->input->set( 'view', 'users' );
        Factory::getApplication()->input->set( 'layout', 'default'  );
        Factory::getApplication()->input->set( 'hidemainmenu', 1 );
        Factory::getApplication()->input->set( 'filter_order', 'ordering' );
        Factory::getApplication()->input->set( 'filter_order_Dir', 'asc' );
        Factory::getApplication()->input->set( 'limitstart', Factory::getApplication()->input->getInt('limitstart') );

        parent::display();
    }
    
    public function verified_edit() {
        $cid = Factory::getApplication()->input->get('cid', [], 'array');

        $model = $this->getUserModelForListActions();
        $model->setListVerifiedEdit();

        Factory::getApplication()->input->set( 'view', 'users' );
        Factory::getApplication()->input->set( 'layout', 'default'  );
        Factory::getApplication()->input->set( 'hidemainmenu', 1 );
        Factory::getApplication()->input->set( 'filter_order', 'ordering' );
        Factory::getApplication()->input->set( 'filter_order_Dir', 'asc' );
        Factory::getApplication()->input->set( 'limitstart', Factory::getApplication()->input->getInt('limitstart') );

        parent::display();
    }
    
    public function not_verified_edit() {
        $cid = Factory::getApplication()->input->get('cid', [], 'array');

        $model = $this->getUserModelForListActions();
        $model->setListNotVerifiedEdit();

        Factory::getApplication()->input->set( 'view', 'users' );
        Factory::getApplication()->input->set( 'layout', 'default'  );
        Factory::getApplication()->input->set( 'hidemainmenu', 1 );
        Factory::getApplication()->input->set( 'filter_order', 'ordering' );
        Factory::getApplication()->input->set( 'filter_order_Dir', 'asc' );
        Factory::getApplication()->input->set( 'limitstart', Factory::getApplication()->input->getInt('limitstart') );

        parent::display();
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
        $cid = Factory::getApplication()->input->get('cid', [], 'array');

        if(count($cid) == 1)
        {
            $model = $this->getUsersModelForPublishActions();
            $model->setPublished();
        }
        else if(count($cid) > 1)
        {
            $model = $this->getUsersModelForPublishActions();
            $model->setPublished();
        }

        $this->setRedirect( Route::_('index.php?option=com_contentbuilder_ng&view=users&form_id='.Factory::getApplication()->input->getInt('form_id',0).'&tmpl='.Factory::getApplication()->input->getCmd('tmpl','').'&limitstart='.Factory::getApplication()->input->getInt('limitstart'), false), Text::_('COM_CONTENTBUILDER_NG_PUBLISHED') );
    }
    
    public function unpublish() {
        $cid = Factory::getApplication()->input->get('cid', [], 'array');

        if(count($cid) == 1)
        {
            $model = $this->getUsersModelForPublishActions();
            $model->setUnpublished();
        }
        else if(count($cid) > 1)
        {
            $model = $this->getUsersModelForPublishActions();
            $model->setUnpublished();
        }

        $this->setRedirect( Route::_('index.php?option=com_contentbuilder_ng&view=users&form_id='.Factory::getApplication()->input->getInt('form_id',0).'&tmpl='.Factory::getApplication()->input->getCmd('tmpl','').'&limitstart='.Factory::getApplication()->input->getInt('limitstart'), false), Text::_('COM_CONTENTBUILDER_NG_UNPUBLISHED') );
    }
    
    public function save($keep_task = false)
    {
        $model = $this->getUserModelForSave();
        $id = $model->store();
        
        if ($id) {
            $msg = Text::_( 'COM_CONTENTBUILDER_NG_SAVED' );
        } else {
            $msg = Text::_( 'COM_CONTENTBUILDER_NG_ERROR' );
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
        $link = 'index.php?option=com_contentbuilder_ng&view=users&form_id='.Factory::getApplication()->input->getInt('form_id',0).'&tmpl='.Factory::getApplication()->input->getCmd('tmpl','').'&limitstart='.$limit.$additionalParams;
        $this->setRedirect(Route::_($link, false), $msg);
    }

    public function cancel()
    {
        $msg = Text::_( 'COM_CONTENTBUILDER_NG_CANCELLED' );
        $this->setRedirect( Route::_('index.php?option=com_contentbuilder_ng&view=users&form_id='.Factory::getApplication()->input->getInt('form_id',0).'&tmpl='.Factory::getApplication()->input->getCmd('tmpl','').'&limitstart=0', false), $msg );
    }

    public function display($cachable = false, $urlparams = array())
    {
        Factory::getApplication()->input->set('tmpl', Factory::getApplication()->input->getWord('tmpl',null));
        Factory::getApplication()->input->set('layout', Factory::getApplication()->input->getWord('layout',null));
        Factory::getApplication()->input->set('view', 'users');

        parent::display();
    }
}
