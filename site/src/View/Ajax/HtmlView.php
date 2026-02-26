<?php
/**
 * @package     ContentBuilder NG
 * @author      Markus Bopp / XDA+GIL
 * @link        https://breezingforms-ng.vcmb.fr
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
*/

namespace CB\Component\Contentbuilderng\Site\View\Ajax;

// No direct access
defined( '_JEXEC' ) or die( 'Restricted access' );

use CB\Component\Contentbuilderng\Administrator\View\Contentbuilderng\HtmlView as BaseHtmlView;
use CB\Component\Contentbuilderng\Site\Model\AjaxModel;

class HtmlView extends BaseHtmlView
{
    function display($tpl = null)
    {
        /** @var AjaxModel $model */
        $model = $this->getModel();

        // Get data from the model
        $data = $model->getData();
        $this->data = $data;
        parent::display($tpl);
    }
}
