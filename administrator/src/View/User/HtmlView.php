<?php
/**
 * @package     ContentBuilder NG
 * @author      Markus Bopp / XDA+GIL
 * @link        https://breezingforms-ng.vcmb.fr
 * @copyright   (C) 2026 by XDA+GIL
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace CB\Component\Contentbuilderng\Administrator\View\User;

// No direct access
\defined('_JEXEC') or die('Restricted access');

use CB\Component\Contentbuilderng\Administrator\View\Contentbuilderng\HtmlView as BaseHtmlView;
use CB\Component\Contentbuilderng\Administrator\Model\UserModel;

class HtmlView extends BaseHtmlView
{
    function display($tpl = null)
    {
        /** @var UserModel $model */
        $model = $this->getModel();
        $subject = $model->getData();
        $this->subject = $subject;
        parent::display($tpl);
    }
}
