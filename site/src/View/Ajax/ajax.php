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

ob_end_clean();
header("Content-type: text/plain; charset=UTF-8");

echo $this->data;

exit;