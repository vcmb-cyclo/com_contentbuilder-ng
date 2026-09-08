<?php

namespace CB\Component\Contentbuilderng\Site\Field;

\defined('_JEXEC') or die;

use CB\Component\Contentbuilderng\Administrator\Helper\RuntimeContextHelper;
use CB\Component\Contentbuilderng\Administrator\Helper\ListLimitHelper;
use CB\Component\Contentbuilderng\Site\Helper\MenuViewDefaultsHelper;
use Joomla\CMS\Form\Field\ListField;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;

final class MenuformsField extends ListField
{
    protected $type = 'Menuforms';

    protected function getOptions(): array
    {
        $options = [
            HTMLHelper::_('select.option', '', Text::_('COM_CONTENTBUILDERNG_SELECT_VIEW_OPTION')),
        ];

        foreach (MenuViewDefaultsHelper::getAll() as $formId => $defaults) {
            $options[] = HTMLHelper::_('select.option', (string) $formId, $defaults['form_name']);
        }

        return array_merge($options, parent::getOptions());
    }

    protected function getInput(): string
    {
        $document = RuntimeContextHelper::getApplication()->getDocument();
        $wa = $document->getWebAssetManager();
        $wa->getRegistry()->addExtensionRegistryFile('com_contentbuilderng');
        $wa->useStyle('com_contentbuilderng.menu-options.css');
        $wa->useScript('com_contentbuilderng.menu-list-options.js');
        $document->addScriptOptions('com_contentbuilderng.menuListOptions', [
            'defaultsByForm' => MenuViewDefaultsHelper::getAll(),
            'useDefaultFormat' => Text::_('COM_CONTENTBUILDERNG_USE_DEFAULT_VALUE'),
            'yesLabel' => Text::_('JYES'),
            'noLabel' => Text::_('JNO'),
            'allLabel' => Text::_('JALL'),
            'globalListLimit' => ListLimitHelper::getGlobalDefault(),
        ]);

        return parent::getInput();
    }
}
