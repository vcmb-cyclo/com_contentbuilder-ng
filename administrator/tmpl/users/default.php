<?php

/**
 * @package     ContentBuilder NG
 * @author      Markus Bopp / XDA+GIL
 * @link        https://breezingforms-ng.vcmb.fr
 * @copyright   Copyright (C) 2026 by XDA+GIL 
 * @license     GNU/GPL
 */



// No direct access
\defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Factory;
use CB\Component\Contentbuilder_ng\Administrator\Helper\ContentbuilderHelper;

HTMLHelper::_('behavior.multiselect');

$ordering  = (string) $this->state->get('list.ordering', 'u.id');
$direction = strtolower((string) $this->state->get('list.direction', 'asc'));
$direction = ($direction === 'desc') ? 'desc' : 'asc';
$search    = $this->state->get('filter.search');
$formId    = (int) Factory::getApplication()->input->getInt('form_id', 0);
$tmpl      = Factory::getApplication()->input->getWord('tmpl', '');

$sortLink = function (string $label, string $field) use ($ordering, $direction, $formId, $tmpl): string {
    $isActive = ($ordering === $field);
    $nextDir = ($isActive && $direction === 'asc') ? 'desc' : 'asc';
    $indicator = $isActive
        ? ($direction === 'asc'
            ? ' <span class="ms-1 fa-solid fa-sort fa-solid fa-sort-up" aria-hidden="true"></span>'
            : ' <span class="ms-1 fa-solid fa-sort fa-solid fa-sort-down" aria-hidden="true"></span>')
        : '';
    $tmplParam = $tmpl !== '' ? '&tmpl=' . $tmpl : '';
    $url = Route::_(
        'index.php?option=com_contentbuilder_ng&view=users&form_id='
        . $formId . $tmplParam . '&list[start]=0&list[ordering]=' . $field
        . '&list[direction]=' . $nextDir
    );

    return '<a href="' . $url . '">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . $indicator . '</a>';
};
?>
<form action="index.php" method="post" name="adminForm" id="adminForm">

    <input class="form-control form-control-sm w-25"
        type="text"
        name="filter_search"
        id="filter_search"
        value="<?php echo htmlspecialchars($search, ENT_QUOTES, 'UTF-8'); ?>"
        onchange="document.adminForm.submit();" />

    <input type="button" class="btn btn-sm btn-primary" value="<?php echo Text::_('COM_CONTENTBUILDER_NG_SEARCH'); ?>"
        onclick="this.form.submit();" />
    <input type="button" class="btn btn-sm btn-primary"
        value="<?php echo Text::_('COM_CONTENTBUILDER_NG_RESET'); ?>"
        onclick="document.getElementById('filter_search').value='';document.adminForm.submit();" />



    <div style="float:right">
        <select class="form-select-sm"
            onchange="if(this.selectedIndex == 1 || this.selectedIndex == 2){document.adminForm.task.value=this.options[this.selectedIndex].value;document.adminForm.submit();}">
            <option> -
                <?php echo Text::_('COM_CONTENTBUILDER_NG_UPDATE_STATUS'); ?> -
            </option>
            <option value="users.publish">
                <?php echo Text::_('COM_CONTENTBUILDER_NG_PUBLISH'); ?>
            </option>
            <option value="users.unpublish">
                <?php echo Text::_('COM_CONTENTBUILDER_NG_UNPUBLISH'); ?>
            </option>
        </select>
        <select class="form-select-sm"
            onchange="if(this.selectedIndex != 0){document.adminForm.task.value=this.options[this.selectedIndex].value;document.adminForm.submit();}">
            <option> -
                <?php echo Text::_('COM_CONTENTBUILDER_NG_SET_VERIFICATION'); ?> -
            </option>
            <option value="verified_view">
                <?php echo Text::_('COM_CONTENTBUILDER_NG_VERIFIED_VIEW'); ?>
            </option>
            <option value="not_verified_view">
                <?php echo Text::_('COM_CONTENTBUILDER_NG_UNVERIFIED_VIEW'); ?>
            </option>
            <option value="verified_new">
                <?php echo Text::_('COM_CONTENTBUILDER_NG_VERIFIED_NEW'); ?>
            </option>
            <option value="not_verified_new">
                <?php echo Text::_('COM_CONTENTBUILDER_NG_UNVERIFIED_NEW'); ?>
            </option>
            <option value="verified_edit">
                <?php echo Text::_('COM_CONTENTBUILDER_NG_VERIFIED_EDIT'); ?>
            </option>
            <option value="not_verified_edit">
                <?php echo Text::_('COM_CONTENTBUILDER_NG_UNVERIFIED_EDIT'); ?>
            </option>
        </select>
    </div>

    <div style="clear:both;"></div>

    <div id="editcell">
        <table class="table table-striped">
            <thead>
                <tr>
                    <th width="5">
                        <?php echo $sortLink('ID', 'u.id'); ?>
                    </th>
                    <th width="20">
                        <?php echo HTMLHelper::_('grid.checkall'); ?>
                    </th>
                    <th>
                        <?php echo $sortLink('Name', 'u.name'); ?>
                    </th>
                    <th>
                        <?php echo $sortLink('Username', 'u.username'); ?>
                    </th>
                    <th>
                        <?php echo $sortLink(Text::_('COM_CONTENTBUILDER_NG_VERIFIED_VIEW'), 'a.verified_view'); ?>
                    </th>
                    <th>
                        <?php echo $sortLink(Text::_('COM_CONTENTBUILDER_NG_VERIFIED_NEW'), 'a.verified_new'); ?>
                    </th>
                    <th>
                        <?php echo $sortLink(Text::_('COM_CONTENTBUILDER_NG_VERIFIED_EDIT'), 'a.verified_edit'); ?>
                    </th>
                    <th width="5">
                        <?php echo $sortLink(Text::_('COM_CONTENTBUILDER_NG_PUBLISHED'), 'a.published'); ?>
                    </th>
                </tr>
            </thead>

            <tbody>
                <?php foreach ($this->items as $i => $item):
                    $checked = HTMLHelper::_('grid.id', $i, $item->id);
                    $link = Route::_('index.php?option=com_contentbuilder_ng&task=user.edit&form_id=' . (int) Factory::getApplication()->input->getInt('form_id', 0) . '&joomla_userid=' . (int) $item->id);
                    if ($item->published === null) {
                        $item->published = 1;
                    }
                    $published = ContentbuilderHelper::listPublish('users', $item, $i);
                    $verified_view = ContentbuilderHelper::listVerifiedView('users', $item, $i);
                    $verified_new = ContentbuilderHelper::listVerifiedNew('users', $item, $i);
                    $verified_edit = ContentbuilderHelper::listVerifiedEdit('users', $item, $i);
                ?>
                    <tr>
                        <td>
                            <?php echo (int) $item->id; ?>
                        </td>
                        <td>
                            <?php echo $checked; ?>
                        </td>
                        <td>
                            <a href="<?php echo $link; ?>">
                                <?php echo htmlspecialchars($item->name, ENT_QUOTES, 'UTF-8'); ?>
                            </a>
                        </td>
                        <td>
                            <a href="<?php echo $link; ?>">
                                <?php echo htmlspecialchars($item->username, ENT_QUOTES, 'UTF-8'); ?>
                            </a>
                        </td>
                        <td>
                            <?php echo $verified_view; ?>
                        </td>
                        <td>
                            <?php echo $verified_new; ?>
                        </td>
                        <td>
                            <?php echo $verified_edit; ?>
                        </td>
                        <td>
                            <?php echo $published; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="9">
                        <?php echo $this->pagination->getListFooter(); ?>
                    </td>
                </tr>
            </tfoot>

        </table>
    </div>

    <input type="hidden" name="option" value="com_contentbuilder_ng" />
    <input type="hidden" name="task" value="" />
    <input type="hidden" name="boxchecked" value="0" />
    <input type="hidden" name="view" value="users" />
    <input type="hidden" name="form_id" value="<?php echo $formId; ?>" />
    <input type="hidden" name="tmpl" value="<?php echo $tmpl; ?>" />
    <input type="hidden" name="list[ordering]" value="<?php echo htmlspecialchars($ordering, ENT_QUOTES, 'UTF-8'); ?>">
    <input type="hidden" name="list[direction]" value="<?php echo htmlspecialchars($direction, ENT_QUOTES, 'UTF-8'); ?>">
    <?php echo HTMLHelper::_('form.token'); ?>
</form>
