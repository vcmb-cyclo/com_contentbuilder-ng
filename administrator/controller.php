<?php
/**
 * @package     ContentBuilder NG
 * @author      Markus Bopp / XDA+GIL
 * @link        https://breezingforms-ng.vcmb.fr
 * @copyright   (C) 2026 by XDA+GIL
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
*/

namespace CB\Component\Contentbuilderng\Administrator;

// No direct access
\defined('_JEXEC') or die('Direct Access to this location is not allowed.');

Use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Controller\BaseController;

class ContentbuilderController extends BaseController
{
    /**
     * Method to display the view
     *
     * @access    public
     */
    function display($cachable = false, $urlparams = array())
    {
        parent::display();

	    if(Factory::getApplication()->input->get('market', '', 'string') == 'true'){
            Factory::getApplication()->redirect('https://breezingforms-ng.vcmb.fr');
        }
    }

}
