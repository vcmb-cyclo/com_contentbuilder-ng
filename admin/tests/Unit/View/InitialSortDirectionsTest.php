<?php

declare(strict_types=1);

namespace CB\Component\Contentbuilderng\Tests\Unit\View;

use PHPUnit\Framework\TestCase;

final class InitialSortDirectionsTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = \dirname(__DIR__, 4);
    }

    public function testEachSortCriterionHasItsOwnPersistedDirection(): void
    {
        $layout = $this->read('admin/layouts/form/advanced_options.php');
        $form = $this->read('admin/forms/form.xml');
        $table = $this->read('admin/src/Table/FormTable.php');
        $install = $this->read('admin/sql/install.sql');
        $migration = $this->read('admin/sql/updates/mysql/6.1.10-RC09-B9.sql');

        foreach (['initial_order_dir', 'initial_order_dir2', 'initial_order_dir3'] as $name) {
            self::assertStringContainsString('jform[' . $name . ']', $layout);
            self::assertStringContainsString('name="' . $name . '"', $form);
            self::assertStringContainsString('public $' . $name, $table);
            self::assertStringContainsString('`' . $name . '`', $install);
        }

        self::assertStringContainsString('`initial_order_dir2`', $migration);
        self::assertStringContainsString('`initial_order_dir3`', $migration);
    }

    public function testBothStorageTypesApplyTheThreeDirections(): void
    {
        foreach (['com_contentbuilderng.php', 'com_breezingformsng.php'] as $file) {
            $source = $this->read('admin/src/types/' . $file);

            self::assertStringContainsString('$initialOrderDirection1', $source);
            self::assertStringContainsString('$initialOrderDirection2', $source);
            self::assertStringContainsString('$initialOrderDirection3', $source);
        }
    }

    public function testUnusedSecondarySortCriteriaDefaultToAscending(): void
    {
        $layout = $this->read('admin/layouts/form/advanced_options.php');
        $form = $this->read('admin/forms/form.xml');
        $menuField = $this->read('site/src/Field/MenulistbuilderField.php');
        $menuScript = $this->read('media/js/menu-list-options.js');
        $migration = $this->read('admin/sql/updates/mysql/6.1.10-RC09-B10.sql');

        foreach (['initial_order_dir2', 'initial_order_dir3'] as $name) {
            self::assertMatchesRegularExpression(
                '/name="' . $name . '"[^>]*default="asc"/',
                $form
            );
            self::assertStringContainsString("`" . $name . "` = 'asc'", $migration);
            self::assertStringContainsString('MODIFY `' . $name . '`', $migration);
        }

        self::assertSame(2, \substr_count($migration, 'ALTER TABLE'));
        self::assertStringNotContainsString('MODIFY COLUMN', $migration);
        self::assertStringContainsString("String(orderField.value) !== '-1'", $layout);
        self::assertStringContainsString("\$sortField === '' ? 'asc'", $menuField);
        self::assertStringContainsString("direction.value = 'asc';", $menuScript);
    }

    private function read(string $relativePath): string
    {
        $path = $this->root . '/' . $relativePath;
        self::assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
