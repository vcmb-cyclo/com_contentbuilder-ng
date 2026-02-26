<?php

/**
 * @package     BreezingCommerce
 * @author      Markus Bopp / XDA+GIL
 * @link        https://breezingforms-ng.vcmb.fr
 * @copyright   Copyright © 2026 by XDA+GIL
 * @license     GNU/GPL
 */

namespace CB\Component\Contentbuilderng\Site\Element;

// No direct access
\defined('_JEXEC') or die('Direct Access to this location is not allowed.');

use Joomla\CMS\Language\Text;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\FormField;
use Joomla\Database\DatabaseInterface;

class JFormFieldCategories extends FormField
{

    protected $type = 'Categories';

    protected function getInput()
    {
        // Initialise variables.
        $options = array();

        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true);

        $query->select('a.id AS value, a.title AS text, a.level');
        $query->from('#__categories AS a');
        $query->join('LEFT', '`#__categories` AS b ON a.lft > b.lft AND a.rgt < b.rgt');

        // Filter by the type
        $query->where('(a.extension = ' . $db->quote('com_content') . ' OR a.parent_id = 0)');

        $query->where('a.published IN (0,1)');
        $query->group('a.id');
        $query->order('a.lft ASC');

        // Get the options.
        $db->setQuery($query);

        // Check for a database error.
        try {
            $options = $db->loadObjectList();
        } catch (\Exception $e) {
            Factory::getApplication()->enqueueMessage($e->getMessage(), 'error');
        } // try

        // Pad the option text with spaces using depth level as a multiplier.
        for ($i = 0, $n = count($options); $i < $n; $i++) {
            // Translate ROOT
            if ($options[$i]->level == 0) {
                $options[$i]->text = Text::_('JGLOBAL_ROOT_PARENT');
            }

            $options[$i]->text = str_repeat('- ', $options[$i]->level) . $options[$i]->text;
        }

        // Initialise variables.
        $user = Factory::getApplication()->getIdentity();

        foreach ($options as $i => $option) {
            // Unset the option if the user isn't authorised for it.
            if (!$user->authorise('core.create', 'com_content.category.' . $option->value)) {
                unset($options[$i]);
            }
        }


        if (isset($row) && !isset($options[0])) {
            if ($row->parent_id == '1') {
                $parent = new \stdClass();
                $parent->text = Text::_('JGLOBAL_ROOT_PARENT');
                array_unshift($options, $parent);
            }
        }

        // Merge any additional options in the XML definition.
        //$options = array_merge(parent::getOptions(), $options);

        $fieldClass = (string) ($this->element['class'] ?: '');
        $out = '<select style="max-width: 200px;" name="' . $this->name . '" id="' . $this->id . '" class="' . $fieldClass . '">' . "\n";

        $out .= '<option value="-2">' . Text::_('COM_CONTENTBUILDERNG_INHERIT') . '</option>' . "\n";

        foreach ($options as $category) {
            $out .= '<option ' . ($this->value == $category->value ? ' selected="selected"' : '') . 'value="' . $category->value . '">' . htmlentities($category->text, ENT_QUOTES, 'UTF-8') . '</option>' . "\n";
        }
        $out .= '</select>' . "\n";

        return $out;
    }
}
