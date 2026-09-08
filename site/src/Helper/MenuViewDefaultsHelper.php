<?php

namespace CB\Component\Contentbuilderng\Site\Helper;

\defined('_JEXEC') or die;

use CB\Component\Contentbuilderng\Administrator\Helper\RuntimeContextHelper;
use CB\Component\Contentbuilderng\Administrator\Helper\ListLimitHelper;
use Joomla\CMS\Form\Form;
use Joomla\Database\DatabaseInterface;

final class MenuViewDefaultsHelper
{
    private const BOOLEAN_COLUMNS = [
        'cb_show_author',
        'cb_show_top_bar',
        'cb_show_bottom_bar',
        'cb_show_details_top_bar',
        'cb_show_details_bottom_bar',
        'show_back_button',
        'cb_filter_in_title',
        'cb_prefix_in_title',
        'show_title_breadcrumb',
        'show_filter',
        'show_state_filter',
        'show_records_per_page',
        'edit_button',
        'export_xls',
        'print_button',
        'list_rating',
        'list_state',
        'list_state_bulk',
    ];

    private static ?array $defaults = null;

    public static function getSelectedFormId(?Form $form, mixed $value = null): int
    {
        $formId = (int) ($form?->getValue('form_id', 'params', 0) ?? 0);

        if ($formId <= 0) {
            $formId = (int) ($form?->getValue('form_id', 'params.settings', 0) ?? 0);
        }

        if ($formId <= 0 && method_exists($form, 'getData')) {
            $data = $form->getData();

            if (is_object($data) && method_exists($data, 'get')) {
                $formId = (int) $data->get('params.form_id', 0);

                if ($formId <= 0) {
                    $formId = (int) $data->get('params.settings.form_id', 0);
                }
            }
        }

        return $formId > 0 ? $formId : (int) $value;
    }

    public static function getAll(): array
    {
        if (self::$defaults !== null) {
            return self::$defaults;
        }

        /** @var DatabaseInterface $db */
        $db = RuntimeContextHelper::getDatabase();
        $columns = array_merge(
            ['id', 'name', 'default_category', 'initial_list_limit', 'maximum_records', 'theme_plugin', 'config'],
            self::BOOLEAN_COLUMNS
        );
        $query = $db->getQuery(true)
            ->select(array_map([$db, 'quoteName'], $columns))
            ->from($db->quoteName('#__contentbuilderng_forms'))
            ->order([$db->quoteName('name') . ' ASC', $db->quoteName('id') . ' ASC']);
        $db->setQuery($query);

        $forms = (array) $db->loadObjectList();
        $categoryIds = [];

        foreach ($forms as $form) {
            $categoryId = (int) $form->default_category;

            if ($categoryId > 0) {
                $categoryIds[] = $categoryId;
            }
        }

        $categoryTitles = [];

        if ($categoryIds !== []) {
            $query = $db->getQuery(true)
                ->select([$db->quoteName('id'), $db->quoteName('title')])
                ->from($db->quoteName('#__categories'))
                ->whereIn($db->quoteName('id'), array_values(array_unique($categoryIds)));
            $db->setQuery($query);
            $categoryTitles = (array) $db->loadAssocList('id', 'title');
        }

        self::$defaults = [];

        foreach ($forms as $form) {
            $formId = (int) $form->id;
            $categoryId = (int) $form->default_category;
            $values = [
                'form_name' => (string) $form->name,
                'cb_category_id' => $categoryId > 0
                    ? (string) ($categoryTitles[$categoryId] ?? '#' . $categoryId)
                    : '',
                'cb_category_menu_filter' => 0,
                'cb_list_limit' => ListLimitHelper::resolveViewValue($form->initial_list_limit),
                'cb_maximum_records' => max(ListLimitHelper::ALL, (int) $form->maximum_records),
                'cb_theme_plugin' => trim((string) $form->theme_plugin) ?: 'thoth',
            ];

            foreach (self::BOOLEAN_COLUMNS as $column) {
                $key = $column === 'show_back_button' ? 'cb_show_details_back_button' : $column;
                $values[$key] = (int) $form->$column === 1 ? 1 : 0;
            }

            self::$defaults[$formId] = $values;
        }

        return self::$defaults;
    }

    public static function get(int $formId): array
    {
        return self::getAll()[$formId] ?? [];
    }
}
