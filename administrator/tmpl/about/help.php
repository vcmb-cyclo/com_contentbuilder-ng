<?php
/**
 * @package     ContentBuilder NG
 * @author      XDA+GIL
 * @link        https://breezingforms-ng.vcmb.fr
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

\defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
?>
<div class="container-fluid p-3">
    <h1 class="h3 mb-3"><?php echo Text::_('COM_CONTENTBUILDERNG_HELP_ABOUT_TITLE'); ?></h1>
    <p class="mb-3"><?php echo Text::_('COM_CONTENTBUILDERNG_HELP_ABOUT_INTRO'); ?></p>
    <ul class="mb-3">
        <li><?php echo Text::_('COM_CONTENTBUILDERNG_HELP_ABOUT_POINT_1'); ?></li>
        <li><?php echo Text::_('COM_CONTENTBUILDERNG_HELP_ABOUT_POINT_2'); ?></li>
        <li><?php echo Text::_('COM_CONTENTBUILDERNG_HELP_ABOUT_POINT_3'); ?></li>
        <li><?php echo Text::_('COM_CONTENTBUILDERNG_HELP_ABOUT_POINT_4'); ?></li>
        <li><?php echo Text::_('COM_CONTENTBUILDERNG_HELP_ABOUT_POINT_5'); ?></li>
        <li><?php echo Text::_('COM_CONTENTBUILDERNG_HELP_ABOUT_POINT_6'); ?></li>
        <li><?php echo Text::_('COM_CONTENTBUILDERNG_HELP_ABOUT_POINT_7'); ?></li>
        <li><?php echo Text::_('COM_CONTENTBUILDERNG_HELP_ABOUT_POINT_8'); ?></li>
        <li><?php echo Text::_('COM_CONTENTBUILDERNG_HELP_ABOUT_POINT_9'); ?></li>
        <li><?php echo Text::_('COM_CONTENTBUILDERNG_HELP_ABOUT_POINT_10'); ?></li>
    </ul>
    <a class="btn btn-primary btn-sm" href="<?php echo Route::_('index.php?option=com_contentbuilderng&view=about'); ?>">
        <?php echo Text::_('COM_CONTENTBUILDERNG_HELP_BACK_TO_ABOUT'); ?>
    </a>
</div>
