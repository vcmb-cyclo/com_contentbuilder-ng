<?php
/**
 * @package     ContentBuilder NG
 * @author      Markus Bopp / XDA+GIL
 * @link        https://breezingforms-ng.vcmb.fr
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
*/

namespace CB\Component\Contentbuilderng\Administrator\Model;

// No direct access
defined( '_JEXEC' ) or die( 'Restricted access' );

use Joomla\Utilities\ArrayHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;

class UserModel extends BaseDatabaseModel
{
    private $_form_id = 0;

    function  __construct($config)
    {
        parent::__construct($config);

        $this->setIds(Factory::getApplication()->input->getInt('joomla_userid',  0), Factory::getApplication()->input->getInt('form_id',  ''));
        
    }

    /*
     * MAIN DETAILS AREA
     */

    /**
     *
     * @param int $id
     */
    function setIds($id, $form_id) {
        // Set id and wipe data
        $this->_id = $id;
        $this->_form_id = $form_id;
        $this->_data = null;
    }

    private function _buildQuery(){
        return 'Select users.*, contentbuilderng_users.limit_edit, contentbuilderng_users.limit_add, contentbuilderng_users.id As cb_id, contentbuilderng_users.form_id, contentbuilderng_users.verification_date_edit, contentbuilderng_users.verification_date_new, contentbuilderng_users.verification_date_view, contentbuilderng_users.verified_view, contentbuilderng_users.verified_new, contentbuilderng_users.verified_edit, contentbuilderng_users.records, contentbuilderng_users.published From #__users As users Left Join #__contentbuilderng_users As contentbuilderng_users On ( users.id = contentbuilderng_users.userid And contentbuilderng_users.form_id = '.Factory::getApplication()->input->getInt('form_id',0).' ) Where users.id = ' . $this->_id;
                
    }
    
    function setListVerifiedView()
    {
        $items	= Factory::getApplication()->input->post->get('cid', [], 'array');
        ArrayHelper::toInteger($items);
        if (count($items)) {
            $cids = $items;
            foreach($cids As $cid){
                $this->getDatabase()->setQuery("Select id From #__contentbuilderng_users Where form_id = ".Factory::getApplication()->input->getInt('form_id',0)." And userid = " . $cid);
                if(!$this->getDatabase()->loadResult() && Factory::getApplication()->input->getInt('form_id',0) && $cid){
                    $this->getDatabase()->setQuery("Insert Into #__contentbuilderng_users (form_id, userid, published) Values (".Factory::getApplication()->input->getInt('form_id',0).", $cid, 1)");
                    $this->getDatabase()->execute();
                }
            }
            
            $this->getDatabase()->setQuery( ' Update #__contentbuilderng_users '.
                        '  Set verified_view = 1 Where form_id = '.$this->_form_id.' And userid In ( '.implode(',', $items) . ')' );
            $this->getDatabase()->execute();
        }
    }
    
    function setListNotVerifiedView()
    {
        $items	= Factory::getApplication()->input->post->get('cid', [], 'array');
        ArrayHelper::toInteger($items);
        if (count($items)) {
            
            $cids = $items;
            foreach($cids As $cid){
                $this->getDatabase()->setQuery("Select id From #__contentbuilderng_users Where form_id = ".Factory::getApplication()->input->getInt('form_id',0)." And userid = " . $cid);
                if(!$this->getDatabase()->loadResult() && Factory::getApplication()->input->getInt('form_id',0) && $cid){
                    $this->getDatabase()->setQuery("Insert Into #__contentbuilderng_users (form_id, userid, published) Values (".Factory::getApplication()->input->getInt('form_id',0).", $cid, 1)");
                    $this->getDatabase()->execute();
                }
            }
            
            $this->getDatabase()->setQuery( ' Update #__contentbuilderng_users '.
                        '  Set verified_view = 0 Where form_id = '.$this->_form_id.' And userid In ( '.implode(',', $items) . ')' );
            $this->getDatabase()->execute();
        }
    }

    function setListVerifiedNew()
    {
        $items	= Factory::getApplication()->input->post->get('cid', [], 'array');
        ArrayHelper::toInteger($items);
        if (count($items)) {
            $cids = $items;
            foreach($cids As $cid){
                $this->getDatabase()->setQuery("Select id From #__contentbuilderng_users Where form_id = ".Factory::getApplication()->input->getInt('form_id',0)." And userid = " . $cid);
                if(!$this->getDatabase()->loadResult() && Factory::getApplication()->input->getInt('form_id',0) && $cid){
                    $this->getDatabase()->setQuery("Insert Into #__contentbuilderng_users (form_id, userid, published) Values (".Factory::getApplication()->input->getInt('form_id',0).", $cid, 1)");
                    $this->getDatabase()->execute();
                }
            }
            
            $this->getDatabase()->setQuery( ' Update #__contentbuilderng_users '.
                        '  Set verified_new = 1 Where form_id = '.$this->_form_id.' And userid In ( '.implode(',', $items) . ')' );
            $this->getDatabase()->execute();
        }
    }
    
    function setListNotVerifiedNew()
    {
        $items	= Factory::getApplication()->input->post->get('cid', [], 'array');
        ArrayHelper::toInteger($items);
        if (count($items)) {
            
            $cids = $items;
            foreach($cids As $cid){
                $this->getDatabase()->setQuery("Select id From #__contentbuilderng_users Where form_id = ".Factory::getApplication()->input->getInt('form_id',0)." And userid = " . $cid);
                if(!$this->getDatabase()->loadResult() && Factory::getApplication()->input->getInt('form_id',0) && $cid){
                    $this->getDatabase()->setQuery("Insert Into #__contentbuilderng_users (form_id, userid, published) Values (".Factory::getApplication()->input->getInt('form_id',0).", $cid, 1)");
                    $this->getDatabase()->execute();
                }
            }
            
            $this->getDatabase()->setQuery( ' Update #__contentbuilderng_users '.
                        '  Set verified_new = 0 Where form_id = '.$this->_form_id.' And userid In ( '.implode(',', $items) . ')' );
            $this->getDatabase()->execute();
        }
    }
    
    function setListVerifiedEdit()
    {
        $items	= Factory::getApplication()->input->post->get('cid', [], 'array');
        ArrayHelper::toInteger($items);
        if (count($items)) {
            $cids = $items;
            foreach($cids As $cid){
                $this->getDatabase()->setQuery("Select id From #__contentbuilderng_users Where form_id = ".Factory::getApplication()->input->getInt('form_id',0)." And userid = " . $cid);
                if(!$this->getDatabase()->loadResult() && Factory::getApplication()->input->getInt('form_id',0) && $cid){
                    $this->getDatabase()->setQuery("Insert Into #__contentbuilderng_users (form_id, userid, published) Values (".Factory::getApplication()->input->getInt('form_id',0).", $cid, 1)");
                    $this->getDatabase()->execute();
                }
            }
            
            $this->getDatabase()->setQuery( ' Update #__contentbuilderng_users '.
                        '  Set verified_edit = 1 Where form_id = '.$this->_form_id.' And userid In ( '.implode(',', $items) . ')' );
            $this->getDatabase()->execute();
        }
    }
    
    function setListNotVerifiedEdit()
    {
        $items	= Factory::getApplication()->input->post->get('cid', [], 'array');
        ArrayHelper::toInteger($items);
        if (count($items)) {
            
            $cids = $items;
            foreach($cids As $cid){
                $this->getDatabase()->setQuery("Select id From #__contentbuilderng_users Where form_id = ".Factory::getApplication()->input->getInt('form_id',0)." And userid = " . $cid);
                if(!$this->getDatabase()->loadResult() && Factory::getApplication()->input->getInt('form_id',0) && $cid){
                    $this->getDatabase()->setQuery("Insert Into #__contentbuilderng_users (form_id, userid, published) Values (".Factory::getApplication()->input->getInt('form_id',0).", $cid, 1)");
                    $this->getDatabase()->execute();
                }
            }
            
            $this->getDatabase()->setQuery( ' Update #__contentbuilderng_users '.
                        '  Set verified_edit = 0 Where form_id = '.$this->_form_id.' And userid In ( '.implode(',', $items) . ')' );
            $this->getDatabase()->execute();
        }
    }
    
    function getData()
    {
        // Lets load the data if it doesn't already exist
        if (empty( $this->_data ))
        {
            $query = $this->_buildQuery();
            $this->getDatabase()->setQuery($query);
            $this->_data = $this->getDatabase()->loadObject();
            
            if($this->_data->published === null){
                $this->_data->published = 1;
            }
            
            return $this->_data;
        }
        return null;
    }
    
    function store()
    {
        $insert = 0;
        $this->getDatabase()->setQuery("Select id From #__contentbuilderng_users Where form_id = ".Factory::getApplication()->input->getInt('form_id',0)." And userid = " . Factory::getApplication()->input->getInt('joomla_userid',0));
        if(!$this->getDatabase()->loadResult() && Factory::getApplication()->input->getInt('form_id',0) && Factory::getApplication()->input->getInt('joomla_userid',0)){
            $this->getDatabase()->setQuery("Insert Into #__contentbuilderng_users (form_id, userid, published) Values (".Factory::getApplication()->input->getInt('form_id',0).", ".Factory::getApplication()->input->getInt('joomla_userid',0).", 1)");
            $this->getDatabase()->execute();
            $insert = $this->getDatabase()->insertid();
        }
        
        $data = Factory::getApplication()->input->post->getArray();
        
        if(!$insert){
            $data['id'] = intval($data['cb_id']);
        }else{
            $data['id'] = $insert;
        }
        
        $data['userid'] = $data['joomla_userid'];
        
        
        $data['verified_view'] = Factory::getApplication()->input->getInt('verified_view',0);
        $data['verified_new'] = Factory::getApplication()->input->getInt('verified_new',0);
        $data['verified_edit'] = Factory::getApplication()->input->getInt('verified_edit',0);
        $data['published'] = Factory::getApplication()->input->getInt('published',0);
        
        $row = $this->getTable('Cbuser');
        
        if (!$row->bind($data)) {
            return false;
        }

        if (!$row->check()) {
            return false;
        }
        
        $storeRes = $row->store();

        if (!$storeRes) {
            return false;
        }
        
        return true;
    }
}
