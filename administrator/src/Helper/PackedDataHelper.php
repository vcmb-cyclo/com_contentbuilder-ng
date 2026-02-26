<?php
/**
 * @package     ContentBuilder NG
 * @author      XDA+GIL
 * @link        https://breezingforms-ng.vcmb.fr
 * @copyright   Copyright © 2026 by XDA+GIL
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace CB\Component\Contentbuilderng\Administrator\Helper;

\defined('_JEXEC') or die('Restricted access');

final class PackedDataHelper
{
    private static function containsIncompleteClass($value): bool
    {
        if ($value instanceof \__PHP_Incomplete_Class) {
            return true;
        }

        if (is_array($value)) {
            foreach ($value as $item) {
                if (self::containsIncompleteClass($item)) {
                    return true;
                }
            }
        } elseif (is_object($value)) {
            foreach ((array) $value as $item) {
                if (self::containsIncompleteClass($item)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Decode base64 packed payload.
     * New format: base64("j:" + json)
     * Legacy format: base64(serialize(...))
     */
    public static function decodePackedData($raw, $default = null, bool $assoc = false)
    {
        if ($raw === null || $raw === '') {
            return $default;
        }

        $decoded = base64_decode((string) $raw, true);
        if ($decoded === false) {
            return $default;
        }

        $jsonPayload = null;
        if (strpos($decoded, 'j:') === 0) {
            $jsonPayload = substr($decoded, 2);
        } elseif (strpos(ltrim($decoded), '{') === 0 || strpos(ltrim($decoded), '[') === 0) {
            $jsonPayload = $decoded;
        }

        if ($jsonPayload !== null) {
            try {
                return json_decode($jsonPayload, $assoc, 512, JSON_THROW_ON_ERROR);
            } catch (\Throwable $e) {
                return $default;
            }
        }

        try {
            $unserialized = @unserialize($decoded, ['allowed_classes' => ['stdClass']]);
        } catch (\Throwable $e) {
            return $default;
        }

        if ($unserialized === false && $decoded !== 'b:0;') {
            return $default;
        }

        if (self::containsIncompleteClass($unserialized)) {
            return $default;
        }

        if ($assoc && (is_array($unserialized) || is_object($unserialized))) {
            $json = json_encode($unserialized);
            if (is_string($json)) {
                $assocDecoded = json_decode($json, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    return $assocDecoded;
                }
            }
        }

        return $unserialized;
    }

    /**
     * Encode payload to base64 JSON (prefixed with j:).
     * Falls back to legacy serialize() if JSON encoding fails.
     */
    public static function encodePackedData($value): string
    {
        try {
            $json = json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            return base64_encode('j:' . $json);
        } catch (\Throwable $e) {
            return base64_encode(serialize($value));
        }
    }
}
