<?php
/**
 *
 * @copyright   Copyright © 2005 - 2020 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace CB\Component\Contentbuilderng\Administrator;

// No direct access
\defined('_JEXEC') or die('Direct Access to this location is not allowed.');

use Joomla\Filesystem\File ;

if (!class_exists(__NAMESPACE__ . '\\CBFile'))
{
	class CBFile extends File {

		public static function read($file){
			return file_get_contents($file);
		}
	}

}