<?php

/**
 * @package     ContentBuilder NG
 * @author      XDA+GIL
 * @link        https://breezingforms-ng.vcmb.fr
 * @copyright   Copyright © 2026 by XDA+GIL
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace CB\Component\Contentbuilderng\Site\Field;

\defined('_JEXEC') or die('Direct Access to this location is not allowed.');

use Joomla\CMS\Form\FormField;
use Joomla\CMS\Language\Text;

class CbmenuresetField extends FormField
{
    protected $type = 'Cbmenureset';

    protected function getInput()
    {
        $defaults = [];
        $skipFields = [
            'form_id',
            'forms',
            'record_id',
            'cb_controller',
            'cb_latest',
            'cb_menu_reset',
        ];

        $xml = $this->form?->getXml();
        if ($xml) {
            $fieldsets = $xml->xpath('//fieldset[@name="settings"]/field');
            if (is_array($fieldsets)) {
                foreach ($fieldsets as $field) {
                    $name = (string) ($field['name'] ?? '');
                    if ($name === '' || in_array($name, $skipFields, true)) {
                        continue;
                    }

                    $defaults[$name] = (string) ($field['default'] ?? '');
                }
            }
        }

        $buttonId = $this->id . '_button';
        $defaultsJson = json_encode($defaults, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
        $buttonLabel = Text::_('COM_CONTENTBUILDERNG_RESET');
        $confirmLabel = Text::_('COM_CONTENTBUILDERNG_RESET_MENU_OPTIONS_CONFIRM');
        if ($confirmLabel === 'COM_CONTENTBUILDERNG_RESET_MENU_OPTIONS_CONFIRM') {
            $confirmLabel = $buttonLabel;
        }
        $tooltipLabel = Text::_('COM_CONTENTBUILDERNG_RESET_MENU_OPTIONS_TOOLTIP');
        if ($tooltipLabel === 'COM_CONTENTBUILDERNG_RESET_MENU_OPTIONS_TOOLTIP') {
            $tooltipLabel = $buttonLabel;
        }
        $confirmText = json_encode($confirmLabel, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
        $tooltipText = htmlspecialchars($tooltipLabel, ENT_QUOTES, 'UTF-8');

        return '
            <div class="mt-3">
                <button type="button" class="btn btn-outline-secondary d-inline-flex align-items-center gap-2 px-3 py-2" id="' . $buttonId . '" title="' . $tooltipText . '" data-bs-toggle="tooltip" data-bs-placement="top" style="font-size:1rem;">
                    <span class="fa-solid fa-rotate-left" aria-hidden="true"></span>
                    <span>' . htmlspecialchars($buttonLabel, ENT_QUOTES, 'UTF-8') . '</span>
                </button>
            </div>
            <script>
            (() => {
                const button = document.getElementById(' . json_encode($buttonId) . ');
                if (!button) {
                    return;
                }

                const defaults = ' . ($defaultsJson ?: '{}') . ';
                const confirmText = ' . ($confirmText ?: '""') . ';

                if (window.bootstrap && typeof window.bootstrap.Tooltip === "function") {
                    window.bootstrap.Tooltip.getOrCreateInstance(button);
                }

                function getFields(name) {
                    return Array.from(document.querySelectorAll(
                        `[name="jform[params][settings][${name}]"], [name="jform[params][${name}]"]`
                    ));
                }

                function trigger(el) {
                    el.dispatchEvent(new Event("input", { bubbles: true }));
                    el.dispatchEvent(new Event("change", { bubbles: true }));
                }

                function resetField(name, value) {
                    const fields = getFields(name);

                    fields.forEach((field) => {
                        const type = String(field.type || "").toLowerCase();

                        if (type === "radio" || type === "checkbox") {
                            field.checked = String(field.value) === String(value);
                            trigger(field);
                            return;
                        }

                        if (field.tagName && String(field.tagName).toLowerCase() === "select" && field.multiple) {
                            Array.from(field.options).forEach((option) => {
                                option.selected = false;
                            });
                            trigger(field);
                            return;
                        }

                        field.value = String(value ?? "");
                        trigger(field);
                    });
                }

                function clearFilterUi() {
                    document.querySelectorAll(
                        "input[id^=\'element_\'][type=\'text\'], input[id^=\'element_\'][type=\'number\']"
                    ).forEach((input) => {
                        input.value = "";
                        trigger(input);
                    });
                }

                button.addEventListener("click", () => {
                    if (confirmText && !window.confirm(confirmText)) {
                        return;
                    }

                    Object.entries(defaults).forEach(([name, value]) => {
                        resetField(name, value);
                    });

                    clearFilterUi();
                });
            })();
            </script>
        ';
    }
}
