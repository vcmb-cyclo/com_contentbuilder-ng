<?php

/**
 * @package     BreezingCommerce
 * @author      Markus Bopp / XDA+GIL
 * @link        https://breezingforms-ng.vcmb.fr
 * @copyright   Copyright © 2026 by XDA+GIL
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace CB\Component\Contentbuilderng\Site\Element;

// No direct access
\defined('_JEXEC') or die('Direct Access to this location is not allowed.');

use Joomla\Database\DatabaseInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\FormField;

class JFormFieldCbfilterhidden extends FormField
{

    protected $type = 'Cbfilterhidden';

    protected function getInput()
    {
        $class = $this->element['class'] ? $this->element['class'] : "text_area";
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $out = '<input type="hidden" name="' . $this->name . '" id="' . $this->id . '" value="' . $this->value . '"/>' . "\n";
        $out .= '
                <script type="text/javascript">
                <!--
                var cb_value = {};
                var currval = "' . str_replace(array("\n", "\r"), array("\\n", ""), addslashes($this->value)) . '";
                
                function contentbuilderng_addValue(element_id, value){
                    cb_value[element_id] = value;
                    var contents = "";
                    for(var x in cb_value){
                        contents += x + "\t" + cb_value[x] + "\n";
                    }
                    document.getElementById("' . $this->id . '").value = contents;
                }
                //-->
                </script>';
        return $out;
    }
}
