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

use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Controller\BaseController;

class ContentbuilderController extends BaseController
{
    /**
     * Method to display the view
     *
     * @access    public
     */
    public function display($cachable = false, $urlparams = array()): void
    {
        parent::display();

        $app = Factory::getApplication();
        if ($app->input->get('market', '', 'string') === 'true') {
            $app->redirect('https://breezingforms-ng.vcmb.fr');
        }
    }
}
