<?php

declare(strict_types=1);

namespace CB\Component\Contentbuilderng\Tests\Unit\View;

use PHPUnit\Framework\TestCase;

final class ListLimitConfigurationTest extends TestCase
{
    private const TRANSLATION_KEYS = [
        'COM_CONTENTBUILDERNG_LIST_LIMIT_DEFAULT_VALUE',
        'COM_CONTENTBUILDERNG_LIST_LIMIT_CUSTOM_VALUE',
        'COM_CONTENTBUILDERNG_LIST_LIMIT_ALL_WARNING',
        'COM_CONTENTBUILDERNG_LIST_LIMIT_OPEN_CHOICES',
    ];

    public function testComponentFactoryDefaultAndViewInheritanceSentinelAreConfigured(): void
    {
        $config = new \SimpleXMLElement((string) \file_get_contents(
            \dirname(__DIR__, 3) . '/config.xml'
        ));
        $globalField = $config->xpath('//field[@name="default_list_limit"]')[0] ?? null;

        self::assertInstanceOf(\SimpleXMLElement::class, $globalField);
        self::assertSame('listlimit', (string) $globalField['type']);
        self::assertSame('20', (string) $globalField['default']);

        $choicesField = $config->xpath('//field[@name="pagination_choices"]')[0] ?? null;
        self::assertInstanceOf(\SimpleXMLElement::class, $choicesField);
        self::assertSame('paginationchoices', (string) $choicesField['type']);
        self::assertSame('paginationchoices', (string) $choicesField['validate']);
        self::assertSame('', (string) $choicesField['default']);

        $form = new \SimpleXMLElement((string) \file_get_contents(
            \dirname(__DIR__, 3) . '/forms/form.xml'
        ));
        $viewField = $form->xpath('//field[@name="initial_list_limit"]')[0] ?? null;

        self::assertInstanceOf(\SimpleXMLElement::class, $viewField);
        self::assertSame('-1', (string) $viewField['default']);
        self::assertSame('-1', (string) $viewField['min']);

        $maximumField = $form->xpath('//field[@name="maximum_records"]')[0] ?? null;
        self::assertInstanceOf(\SimpleXMLElement::class, $maximumField);
        self::assertSame('0', (string) $maximumField['default']);
        self::assertSame('0', (string) $maximumField['min']);
    }

    public function testMaximumRecordsIsPersistedAndAddedByTheB6Schema(): void
    {
        $root = \dirname(__DIR__, 4);
        $update = (string) \file_get_contents(
            $root . '/admin/sql/updates/mysql/6.1.10-RC09-B6.sql'
        );
        $install = (string) \file_get_contents($root . '/admin/sql/install.sql');
        $table = (string) \file_get_contents($root . '/admin/src/Table/FormTable.php');
        $model = (string) \file_get_contents($root . '/admin/src/Model/FormModel.php');
        $layout = (string) \file_get_contents($root . '/admin/layouts/form/advanced_options.php');

        self::assertStringContainsString('ADD COLUMN `maximum_records` INT NOT NULL DEFAULT 0', $update);
        self::assertStringContainsString('`maximum_records`', $install);
        self::assertStringContainsString('public $maximum_records = 0;', $table);
        self::assertStringContainsString("array_key_exists('maximum_records', \$jform)", $model);
        self::assertStringContainsString('name="maximum_records"', $layout);
        self::assertStringContainsString('data-cb-list-limit-mode="global"', $layout);
    }

    public function testDetailCapabilityIsAddedByTheB6SchemaAndDefaultsToEnabled(): void
    {
        $root = \dirname(__DIR__, 4);
        $update = (string) \file_get_contents($root . '/admin/sql/updates/mysql/6.1.10-RC09-B6.sql');
        $install = (string) \file_get_contents($root . '/admin/sql/install.sql');
        $table = (string) \file_get_contents($root . '/admin/src/Table/ElementoptionsTable.php');
        $schemaService = (string) \file_get_contents($root . '/admin/src/Service/SchemaService.php');
        $installer = (string) \file_get_contents($root . '/script.php');

        self::assertStringContainsString('ADD COLUMN `detail_include` TINYINT(1) NOT NULL DEFAULT 1', $update);
        self::assertStringContainsString('`detail_include`', $install);
        self::assertStringContainsString('public $detail_include = 1;', $table);
        self::assertStringContainsString('public function ensureElementsDetailIncludeColumn(): void', $schemaService);
        self::assertStringContainsString('$this->ensureElementsDetailIncludeColumn();', $installer);
        self::assertStringContainsString('$this->schemaService->ensureElementsDetailIncludeColumn();', $installer);
    }

    public function testBreezingFormsRecordTypeNormalizationHandlesCanonicalDuplicates(): void
    {
        $installer = (string) \file_get_contents(\dirname(__DIR__, 4) . '/script.php');

        self::assertStringContainsString('private function normalizeLegacyBreezingFormsRecordTypes(', $installer);
        self::assertStringContainsString("->delete(\$db->quoteName('#__contentbuilderng_records'))", $installer);
        self::assertStringContainsString('$canonicalId > 0 && $canonicalId !== $id', $installer);
    }

    public function testSchemaChangesOnlyTheDefaultForExistingRows(): void
    {
        $update = (string) \file_get_contents(
            \dirname(__DIR__, 3) . '/sql/updates/mysql/6.1.10-RC06.sql'
        );

        self::assertStringContainsString("DEFAULT '-1'", $update);
        self::assertStringContainsString('MODIFY `initial_list_limit`', $update);
        self::assertStringNotContainsString('MODIFY COLUMN', $update);
        self::assertStringNotContainsString('UPDATE ', \strtoupper($update));
    }

    public function testEveryListMenuAcceptsAllAndKeepsInheritanceEmpty(): void
    {
        foreach (['default.xml', 'listcard.xml', 'listcompact.xml', 'listtiles.xml'] as $file) {
            $xml = new \SimpleXMLElement((string) \file_get_contents(
                \dirname(__DIR__, 4) . '/site/tmpl/list/' . $file
            ));
            $field = $xml->xpath('//field[@name="cb_list_limit"]')[0] ?? null;

            self::assertInstanceOf(\SimpleXMLElement::class, $field, $file);
            self::assertSame('', (string) $field['default'], $file);
            self::assertSame('0', (string) $field['min'], $file);
        }
    }

    public function testListLimitTranslationsAreCompleteInEveryAdminLanguageDomain(): void
    {
        $languageRoot = \dirname(__DIR__, 3) . '/language';

        foreach (['en-GB', 'fr-FR', 'de-DE'] as $locale) {
            foreach (['com_contentbuilderng.ini', 'com_contentbuilderng.menu.ini', 'com_contentbuilderng.sys.ini'] as $file) {
                $translations = \parse_ini_file($languageRoot . '/' . $locale . '/' . $file);

                self::assertIsArray($translations, $locale . '/' . $file);
                foreach (self::TRANSLATION_KEYS as $key) {
                    self::assertArrayHasKey($key, $translations, $locale . '/' . $file);
                    self::assertNotSame('', $translations[$key], $locale . '/' . $file . ': ' . $key);
                }
                self::assertSame(1, \substr_count($translations[self::TRANSLATION_KEYS[0]], '%s'));
            }

            $translations = \parse_ini_file($languageRoot . '/' . $locale . '/com_contentbuilderng.ini');
            self::assertArrayHasKey('COM_CONTENTBUILDERNG_CONFIG_DEFAULT_LIST_LIMIT_LABEL', $translations);
            self::assertArrayHasKey('COM_CONTENTBUILDERNG_CONFIG_DEFAULT_LIST_LIMIT_DESC', $translations);
            self::assertArrayHasKey('COM_CONTENTBUILDERNG_CONFIG_PAGINATION_CHOICES_LABEL', $translations);
            self::assertArrayHasKey('COM_CONTENTBUILDERNG_CONFIG_PAGINATION_CHOICES_DESC', $translations);
            self::assertArrayHasKey('COM_CONTENTBUILDERNG_CONFIG_PAGINATION_CHOICES_INVALID', $translations);
            self::assertArrayHasKey('COM_CONTENTBUILDERNG_MAXIMUM_RECORDS', $translations);
            self::assertArrayHasKey('COM_CONTENTBUILDERNG_MAXIMUM_RECORDS_DESC', $translations);
        }
    }

    public function testSharedControlUsesOneJoomlaStyledEditableDropdown(): void
    {
        $field = (string) \file_get_contents(
            \dirname(__DIR__, 3) . '/src/Field/ListlimitField.php'
        );
        $menuField = (string) \file_get_contents(
            \dirname(__DIR__, 4) . '/site/src/Field/MenunumberField.php'
        );
        $menuFormsField = (string) \file_get_contents(
            \dirname(__DIR__, 4) . '/site/src/Field/MenuformsField.php'
        );
        $menuScript = (string) \file_get_contents(
            \dirname(__DIR__, 4) . '/media/js/menu-list-options.js'
        );
        $script = (string) \file_get_contents(
            \dirname(__DIR__, 4) . '/media/js/list-limit-field.js'
        );

        self::assertStringContainsString('class ListlimitField extends FormField', $field);
        self::assertStringNotContainsString('NumberField', $field);
        self::assertStringContainsString('class="input-group cb-list-limit-control"', $field);
        self::assertStringContainsString('data-bs-toggle="dropdown"', $field);
        self::assertStringContainsString('class MenunumberField extends ListlimitField', $menuField);
        self::assertStringContainsString('?? ListLimitHelper::getGlobalDefault();', $menuField);
        self::assertStringContainsString("'globalListLimit' => ListLimitHelper::getGlobalDefault()", $menuFormsField);
        self::assertStringContainsString("key === 'cb_list_limit' ? config.globalListLimit : ''", $menuScript);
        self::assertStringContainsString("[data-cb-list-limit-control]", $script);
        self::assertStringContainsString('commitTypedValue', $script);
    }
}
