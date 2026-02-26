

<?php
/**
 * @package     ContentBuilder NG
 * @author      Xavier DANO
 * @link        https://breezingforms-ng.vcmb.fr
 * @copyright   Copyright © 2026 by XDA+GIL
 * @license     GNU/GPL
 */

// No direct access
\defined('_JEXEC') or die;

use Joomla\CMS\Factory;

$app = Factory::getApplication();

// Boot moderne : charge services/provider.php, extension class, MVCFactory, etc.
$app->bootComponent('com_contentbuilderng')
    ->getDispatcher($app)
    ->dispatch();
