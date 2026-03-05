<?php
/**
 * @package     ContentBuilder NG
 * @author      XDA+GIL
 * @link        https://breezingforms-ng.vcmb.fr
 * @copyright   Copyright © 2026 by XDA+GIL
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace CB\Component\Contentbuilderng\Administrator\View\Configtransfer;

\defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Application\AdministratorApplication;
use Joomla\CMS\Document\HtmlDocument;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\CMS\Uri\Uri;
use Joomla\Database\DatabaseInterface;

class HtmlView extends BaseHtmlView
{
    private const CONFIG_TRANSFER_SELECTION_STATE_KEY = 'com_contentbuilderng.configtransfer.selection';
    protected array $configSections = [];
    protected array $forms = [];
    protected array $storages = [];
    protected array $selectedSections = [];
    protected array $selectedFormIds = [];
    protected array $selectedStorageIds = [];
    protected array $importReport = [];
    protected string $mode = 'export';

    public function display($tpl = null)
    {
        /** @var AdministratorApplication $app */
        $app = Factory::getApplication();
        $user = $app->getIdentity();

        if (!$user->authorise('core.manage', 'com_contentbuilderng')) {
            throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        /** @var HtmlDocument $document */
        $document = $this->getDocument();
        $wa = $document->getWebAssetManager();
        $wa->registerAndUseStyle(
            'com_contentbuilderng.admin',
            'COM_CONTENTBUILDERNG/admin.css',
            [],
            ['media' => 'all']
        );

        $wa->addInlineStyle(
            '.icon-logo_left{
                background-image:url(' . Uri::root(true) . '/media/com_contentbuilderng/images/logo_left.png);
                background-size:contain;
                background-repeat:no-repeat;
                background-position:center;
                display:inline-block;
                width:48px;
                height:48px;
                vertical-align:middle;
            }'
        );

        ToolbarHelper::title(
            Text::_('COM_CONTENTBUILDERNG') . ' :: ' . Text::_('COM_CONTENTBUILDERNG_ABOUT_CONFIG_TRANSFER_TITLE'),
            'logo_left'
        );

        ToolbarHelper::custom('configtransfer.back', 'arrow-left', '', 'JTOOLBAR_BACK', false);
        ToolbarHelper::help(
            'COM_CONTENTBUILDERNG_HELP_ABOUT_TITLE',
            false,
            Uri::base() . 'index.php?option=com_contentbuilderng&view=about&layout=help&tmpl=component'
        );

        $this->mode = $app->input->getCmd('mode', 'export');
        if (!in_array($this->mode, ['export', 'import'], true)) {
            $this->mode = 'export';
        }

        $this->configSections = [
            'component_params' => [
                'label' => Text::_('COM_CONTENTBUILDERNG_ABOUT_CONFIG_SECTION_COMPONENT_PARAMS'),
                'description' => Text::_('COM_CONTENTBUILDERNG_ABOUT_CONFIG_SECTION_COMPONENT_PARAMS_DESC'),
            ],
            'forms' => [
                'label' => Text::_('COM_CONTENTBUILDERNG_ABOUT_CONFIG_SECTION_FORMS'),
                'description' => Text::_('COM_CONTENTBUILDERNG_ABOUT_CONFIG_SECTION_FORMS_DESC'),
            ],
            'elements' => [
                'label' => Text::_('COM_CONTENTBUILDERNG_ABOUT_CONFIG_SECTION_ELEMENTS'),
                'description' => Text::_('COM_CONTENTBUILDERNG_ABOUT_CONFIG_SECTION_ELEMENTS_DESC'),
            ],
            'list_states' => [
                'label' => Text::_('COM_CONTENTBUILDERNG_ABOUT_CONFIG_SECTION_LIST_STATES'),
                'description' => Text::_('COM_CONTENTBUILDERNG_ABOUT_CONFIG_SECTION_LIST_STATES_DESC'),
            ],
            'storages' => [
                'label' => Text::_('COM_CONTENTBUILDERNG_ABOUT_CONFIG_SECTION_STORAGES'),
                'description' => Text::_('COM_CONTENTBUILDERNG_ABOUT_CONFIG_SECTION_STORAGES_DESC'),
            ],
            'storage_fields' => [
                'label' => Text::_('COM_CONTENTBUILDERNG_ABOUT_CONFIG_SECTION_STORAGE_FIELDS'),
                'description' => Text::_('COM_CONTENTBUILDERNG_ABOUT_CONFIG_SECTION_STORAGE_FIELDS_DESC'),
            ],
            'resource_access' => [
                'label' => Text::_('COM_CONTENTBUILDERNG_ABOUT_CONFIG_SECTION_RESOURCE_ACCESS'),
                'description' => Text::_('COM_CONTENTBUILDERNG_ABOUT_CONFIG_SECTION_RESOURCE_ACCESS_DESC'),
            ],
        ];

        $this->forms = $this->loadForms();
        $this->storages = $this->loadStorages();
        $this->hydrateSelectionState($app);
        $importReport = $app->getUserState('com_contentbuilderng.about.import', []);
        $this->importReport = is_array($importReport) ? $importReport : [];
        $app->setUserState('com_contentbuilderng.about.import', []);

        parent::display($tpl);
    }

    private function loadForms(): array
    {
        try {
            $db = Factory::getContainer()->get(DatabaseInterface::class);
            $query = $db->getQuery(true)
                ->select([
                    $db->quoteName('id'),
                    $db->quoteName('name'),
                    $db->quoteName('published'),
                    $db->quoteName('type'),
                    $db->quoteName('reference_id'),
                ])
                ->from($db->quoteName('#__contentbuilderng_forms'))
                ->order($db->quoteName('name') . ' ASC, ' . $db->quoteName('id') . ' ASC');
            $db->setQuery($query);
            return (array) $db->loadAssocList();
        } catch (\Throwable $e) {
            return [];
        }
    }

    private function loadStorages(): array
    {
        try {
            $db = Factory::getContainer()->get(DatabaseInterface::class);
            $query = $db->getQuery(true)
                ->select([
                    $db->quoteName('id'),
                    $db->quoteName('title'),
                    $db->quoteName('name'),
                    $db->quoteName('bytable'),
                ])
                ->from($db->quoteName('#__contentbuilderng_storages'))
                ->order($db->quoteName('title') . ' ASC, ' . $db->quoteName('name') . ' ASC, ' . $db->quoteName('id') . ' ASC');
            $db->setQuery($query);
            return (array) $db->loadAssocList();
        } catch (\Throwable $e) {
            return [];
        }
    }

    private function hydrateSelectionState(AdministratorApplication $app): void
    {
        $selection = (array) $app->getUserState(self::CONFIG_TRANSFER_SELECTION_STATE_KEY, []);
        $isTouched = (int) ($selection['touched'] ?? 0) === 1;

        $allowedSections = array_keys($this->configSections);
        $allowedFormIds = array_values(array_filter(array_map(static fn(array $row): int => (int) ($row['id'] ?? 0), $this->forms), static fn(int $id): bool => $id > 0));
        $allowedStorageIds = array_values(array_filter(array_map(static fn(array $row): int => (int) ($row['id'] ?? 0), $this->storages), static fn(int $id): bool => $id > 0));

        $selectedSections = $this->normalizeSections((array) ($selection['sections'] ?? []), $allowedSections);
        $selectedFormIds = $this->normalizeIds((array) ($selection['form_ids'] ?? []), $allowedFormIds);
        $selectedStorageIds = $this->normalizeIds((array) ($selection['storage_ids'] ?? []), $allowedStorageIds);

        if (!$isTouched) {
            $selectedSections = $allowedSections;
            $selectedFormIds = $allowedFormIds;
            $selectedStorageIds = $allowedStorageIds;
        }

        $this->selectedSections = $selectedSections;
        $this->selectedFormIds = $selectedFormIds;
        $this->selectedStorageIds = $selectedStorageIds;
    }

    private function normalizeSections(array $values, array $allowed): array
    {
        $clean = [];

        foreach ($values as $value) {
            $key = strtolower((string) $value);
            $key = preg_replace('/[^a-z0-9_]/', '', $key) ?? '';
            if ($key !== '' && in_array($key, $allowed, true)) {
                $clean[] = $key;
            }
        }

        return array_values(array_unique($clean));
    }

    private function normalizeIds(array $values, array $allowed): array
    {
        $allowedMap = array_fill_keys($allowed, true);
        $clean = [];

        foreach ($values as $value) {
            $id = (int) $value;
            if ($id > 0 && isset($allowedMap[$id])) {
                $clean[] = $id;
            }
        }

        return array_values(array_unique($clean));
    }
}
