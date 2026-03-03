<?php
/**
 * @package     ContentBuilder NG
 * @author      Markus Bopp / XDA+GIL
 * @link        https://breezingforms-ng.vcmb.fr
 * @copyright   Copyright © 2024-2026 by XDA+GIL
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
*/



// No direct access
\defined('_JEXEC') or die('Direct Access to this location is not allowed.');

use Joomla\CMS\Factory;
use Joomla\CMS\Application\CMSApplication;

$app = Factory::getApplication();
/** @var CMSApplication $app */

$app->setHeader('Content-Type', 'text/plain; charset=UTF-8', true);

echo (string) ($this->data ?? '');
