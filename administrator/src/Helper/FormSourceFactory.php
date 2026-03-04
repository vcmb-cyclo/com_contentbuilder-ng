<?php
/**
 * @package     ContentBuilder NG
 * @author      XDA+GIL
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace CB\Component\Contentbuilderng\Administrator\Helper;

\defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Factory;

final class FormSourceFactory
{
    /**
     * Resolve a source form object using Joomla 6 namespaced type classes first,
     * and fallback to legacy dynamic loader for custom/third-party types.
     *
     * @param   string     $type
     * @param   int|string $referenceId
     *
     * @return  object|null
     */
    public static function getForm(string $type, $referenceId)
    {
        static $cache = [];

        $type = trim($type);
        $referenceId = (int) $referenceId;

        if ($type === '' || $referenceId <= 0) {
            return null;
        }

        if (isset($cache[$type][$referenceId])) {
            return $cache[$type][$referenceId];
        }

        $resolved = self::createKnownTypeForm($type, $referenceId);

        if (!is_array($cache)) {
            $cache = [];
        }
        $cache[$type][$referenceId] = $resolved;

        return is_object($resolved) ? $resolved : null;
    }

    /**
     * Instantiate known source type classes directly.
     */
    private static function createKnownTypeForm(string $type, int $referenceId)
    {
        $normalizedType = $type === 'com_contentbuilder' ? 'com_contentbuilderng' : $type;

        $classMap = [
            'com_contentbuilderng' => 'CB\\Component\\Contentbuilderng\\Administrator\\types\\contentbuilderng_com_contentbuilderng',
            'com_breezingforms' => 'CB\\Component\\Contentbuilderng\\Administrator\\types\\contentbuilderng_com_breezingforms',
        ];

        if (!isset($classMap[$normalizedType])) {
            return null;
        }

        $file = JPATH_ADMINISTRATOR . '/components/com_contentbuilderng/src/types/' . $normalizedType . '.php';
        if (is_file($file)) {
            require_once $file;
        }

        $class = $classMap[$normalizedType];
        if (!class_exists($class)) {
            return null;
        }

        $app = Factory::getApplication();
        $allowUnpublishedSource = $app->isClient('administrator') || $app->input->getBool('cb_preview_ok', false);

        $form = new $class($referenceId);
        $exists = !property_exists($form, 'exists') || (bool) ($form->exists ?? false);

        if ($allowUnpublishedSource && !$exists) {
            try {
                $previewForm = new $class($referenceId, false);
                if (is_object($previewForm)) {
                    $form = $previewForm;
                }
            } catch (\ArgumentCountError|\TypeError $e) {
                // Type class does not support an unpublished flag; keep initial instance.
            }
        }

        return $form;
    }
}
