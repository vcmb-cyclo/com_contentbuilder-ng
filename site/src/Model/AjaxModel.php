<?php
/**
 * @package     ContentBuilder NG
 * @author      Markus Bopp / XDA+GIL
 * @link        https://breezingforms-ng.vcmb.fr
 * @copyright   Copyright © 2026 by XDA+GIL
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace CB\Component\Contentbuilderng\Site\Model;

// No direct access
\defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\CMS\Session\Session;
use CB\Component\Contentbuilderng\Administrator\Helper\ContentbuilderLegacyHelper;
use Joomla\CMS\Application\CMSApplicationInterface;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\Input\Input;

class AjaxModel extends BaseDatabaseModel
{

    private $frontend = false;
    private $_subject = '';

    public function __construct(
        $config,
        MVCFactoryInterface $factory
    ) {
        // IMPORTANT : on transmet factory/app/input à ListModel
        parent::__construct($config, $factory);

        $this->frontend = Factory::getApplication()->isClient('site');

        $app = Factory::getApplication();
        $option = 'com_contentbuilderng';

        $this->_id = Factory::getApplication()->input->getInt('id', 0);
        $this->_subject = Factory::getApplication()->input->getCmd('subject', '');

    }

    function getData()
    {
        switch ($this->_subject) {
            case 'get_unique_values':
                if ($this->frontend) {
                    if (!ContentbuilderLegacyHelper::authorizeFe('listaccess')) {
                        return json_encode(array('code' => 1, 'msg' => Text::_('COM_CONTENTBUILDERNG_PERMISSIONS_VIEW_NOT_ALLOWED')));
                    }
                } else {
                    if (!ContentbuilderLegacyHelper::authorize('listaccess')) {
                        return json_encode(array('code' => 1, 'msg' => Text::_('COM_CONTENTBUILDERNG_PERMISSIONS_VIEW_NOT_ALLOWED')));
                    }
                }

                $this->getDatabase()->setQuery("Select `type`, `reference_id`, `rating_slots` From #__contentbuilderng_forms Where id = " . $this->_id);
                $result = $this->getDatabase()->loadAssoc();

                $form = ContentbuilderLegacyHelper::getForm($result['type'], $result['reference_id']);

                if (!$form || !$form->exists) {
                    return json_encode(array('code' => 2, 'msg' => Text::_('COM_CONTENTBUILDERNG_FORM_ERROR')));
                }

                $values = $form->getUniqueValues(Factory::getApplication()->input->getCmd('field_reference_id', ''), Factory::getApplication()->input->getCmd('where_field', ''), Factory::getApplication()->input->get('where', '', 'string'));

                return json_encode(array('code' => 0, 'field_reference_id' => Factory::getApplication()->input->getCmd('field_reference_id', ''), 'msg' => $values));


                break;

            case 'rating':

                if ($this->frontend) {
                    if (!ContentbuilderLegacyHelper::authorizeFe('rating')) {
                        return json_encode(array('code' => 1, 'msg' => Text::_('COM_CONTENTBUILDERNG_RATING_NOT_ALLOWED')));
                    }
                } else {
                    if (!ContentbuilderLegacyHelper::authorize('rating')) {
                        return json_encode(array('code' => 1, 'msg' => Text::_('COM_CONTENTBUILDERNG_RATING_NOT_ALLOWED')));
                    }
                }

                if (strtoupper((string) Factory::getApplication()->input->getMethod()) !== 'POST') {
                    return json_encode(array('code' => 1, 'msg' => Text::_('JINVALID_TOKEN')));
                }
                if (!Session::checkToken('post') && !Session::checkToken('get')) {
                    return json_encode(array('code' => 1, 'msg' => Text::_('JINVALID_TOKEN')));
                }

                $this->getDatabase()->setQuery("Select `type`, `reference_id`, `rating_slots` From #__contentbuilderng_forms Where id = " . $this->_id);
                $result = $this->getDatabase()->loadAssoc();

                $form = ContentbuilderLegacyHelper::getForm($result['type'], $result['reference_id']);

                if (!$form || !$form->exists) {
                    return json_encode(array('code' => 2, 'msg' => Text::_('COM_CONTENTBUILDERNG_FORM_ERROR')));
                }

                $rating = 0;

                switch ($result['rating_slots']) {
                    case 1:
                        $rating = 1;
                        //$rating = 5;
                        break;
                    case 2:
                        $rating = Factory::getApplication()->input->getInt('rate', 5);
                        if ($rating > 5)
                            $rating = 5;
                        if ($rating < 4)
                            $rating = 0;

                        //if($rating == 2) $rating = 5;
                        break;
                    case 3:
                        $rating = Factory::getApplication()->input->getInt('rate', 3);
                        if ($rating > 3)
                            $rating = 3;
                        if ($rating < 1)
                            $rating = 1;

                        //if($rating == 2) $rating = 3;
                        //if($rating == 3) $rating = 5;
                        break;
                    case 4:
                        $rating = Factory::getApplication()->input->getInt('rate', 4);
                        if ($rating > 4)
                            $rating = 4;
                        if ($rating < 1)
                            $rating = 1;

                        //if($rating == 3) $rating = 4;
                        //if($rating == 4) $rating = 5;
                        break;
                    case 5:
                        $rating = Factory::getApplication()->input->getInt('rate', 5);
                        if ($rating > 5)
                            $rating = 5;
                        if ($rating < 1)
                            $rating = 1;
                        break;
                }

                if ($result['rating_slots'] == 2 || $rating) {

                    $_now = Factory::getDate();

                    // clear rating cache
                    $___now = $_now->toSql();

                    $this->getDatabase()->setQuery("Delete From #__contentbuilderng_rating_cache Where Datediff('" . $___now . "', `date`) >= 1");
                    $this->getDatabase()->execute();

                    // test if already voted
                    $this->getDatabase()->setQuery("Select `form_id` From #__contentbuilderng_rating_cache Where `record_id` = " . $this->getDatabase()->quote(Factory::getApplication()->input->getCmd('record_id', '')) . " And `form_id` = " . $this->_id . " And `ip` = " . $this->getDatabase()->quote($_SERVER['REMOTE_ADDR']));
                    $cached = $this->getDatabase()->loadResult();
                    $rated = Factory::getApplication()->getSession()->get('rated' . $this->_id . Factory::getApplication()->input->getCmd('record_id', ''), false, 'com_contentbuilderng.rating');

                    if ($rated || $cached) {
                        return json_encode(array('code' => 1, 'msg' => Text::_('COM_CONTENTBUILDERNG_RATED_ALREADY')));
                    } else {
                        Factory::getApplication()->getSession()->set('rated' . $this->_id . Factory::getApplication()->input->getCmd('record_id', ''), true, 'com_contentbuilderng.rating');
                    }

                    // adding vote
                    $this->getDatabase()->setQuery("Update #__contentbuilderng_records Set rating_count = rating_count + 1, rating_sum = rating_sum + " . $rating . ", lastip = " . $this->getDatabase()->quote($_SERVER['REMOTE_ADDR']) . " Where `type` = " . $this->getDatabase()->quote($result['type']) . " And `reference_id` = " . $this->getDatabase()->quote($result['reference_id']) . " And `record_id` = " . $this->getDatabase()->quote(Factory::getApplication()->input->getCmd('record_id', '')));
                    $this->getDatabase()->execute();

                    // adding vote to cache
                    $___now = $_now->toSql();
                    $this->getDatabase()->setQuery("Insert Into #__contentbuilderng_rating_cache (`record_id`,`form_id`,`ip`,`date`) Values (" . $this->getDatabase()->quote(Factory::getApplication()->input->getCmd('record_id', '')) . ", " . $this->_id . "," . $this->getDatabase()->quote($_SERVER['REMOTE_ADDR']) . ",'" . $___now . "')");
                    $this->getDatabase()->execute();

                    // updating article's votes if there is an article bound to the record & view
                    $this->getDatabase()->setQuery("Select a.article_id From #__contentbuilderng_articles As a, #__content As c Where c.id = a.article_id And (c.state = 1 Or c.state = 0) And a.form_id = " . $this->_id . " And a.record_id = " . $this->getDatabase()->quote(Factory::getApplication()->input->getCmd('record_id', '')));
                    $article_id = $this->getDatabase()->loadResult();

                    if ($article_id) {

                        $this->getDatabase()->setQuery("Select content_id From #__content_rating Where content_id = " . $article_id);
                        $exists = $this->getDatabase()->loadResult();

                        if ($exists) {
                            $this->getDatabase()->setQuery("
                                Update 
                                    #__content_rating As cr, 
                                    #__contentbuilderng_records As cbr, 
                                    #__contentbuilderng_articles As cba
                                Set
                                    cr.rating_count = cbr.rating_count,
                                    cr.rating_sum = cbr.rating_sum,
                                    cr.lastip = cbr.lastip
                                Where
                                    cbr.record_id = " . $this->getDatabase()->quote(Factory::getApplication()->input->getCmd('record_id', '')) . "
                                And
                                    cbr.record_id = cba.record_id
                                And
                                    cbr.reference_id = " . $this->getDatabase()->quote($result['reference_id']) . "
                                And
                                    cbr.`type` = " . $this->getDatabase()->quote($result['type']) . " 
                                And 
                                    cba.form_id = " . $this->_id . "
                                And
                                    cr.content_id = cba.article_id
                            ");
                            $this->getDatabase()->execute();
                        } else {
                            $this->getDatabase()->setQuery("
                                Insert Into 
                                    #__content_rating 
                                (
                                    content_id,
                                    rating_sum,
                                    rating_count,
                                    lastip
                                ) 
                                Values
                                (
                                    $article_id,
                                    $rating,
                                    1,
                                    " . $this->getDatabase()->quote($_SERVER['REMOTE_ADDR']) . "
                                )");
                            $this->getDatabase()->execute();
                        }
                    }
                }

                return json_encode(array('code' => 0, 'msg' => Text::_('COM_CONTENTBUILDERNG_THANK_YOU_FOR_RATING')));
                break;
        }
        return null;
    }
}
