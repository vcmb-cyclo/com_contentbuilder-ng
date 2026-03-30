<?php

declare(strict_types=1);

namespace CB\Component\Contentbuilderng\Tests\Unit\Helper;

use CB\Component\Contentbuilderng\Administrator\Helper\FormDisplayColumnsHelper;
use Joomla\Database\DatabaseInterface;
use PHPUnit\Framework\TestCase;

final class FormDisplayColumnsHelperTestDatabase implements DatabaseInterface
{
    /** @var array<string,string> */
    private array $columns;
    /** @var array<int,string> */
    public array $queries = [];
    public int $executions = 0;

    /**
     * @param array<string,string> $columns
     */
    public function __construct(array $columns)
    {
        $this->columns = $columns;
    }

    public function getPrefix(): string
    {
        return 'jos_';
    }

    public function getTableColumns(string $table, bool $type = true): array
    {
        return $this->columns;
    }

    public function quoteName(string $name): string
    {
        return '`' . $name . '`';
    }

    public function setQuery(string $query): void
    {
        $this->queries[] = $query;
    }

    public function execute(): void
    {
        $this->executions++;
    }
}

final class FormDisplayColumnsHelperTest extends TestCase
{
    public function testRequiredColumnsReturnsExpectedDefinitions(): void
    {
        self::assertSame([
            'new_button' => 'TINYINT(1) NOT NULL DEFAULT 0',
            'button_bar_sticky' => 'TINYINT(1) NOT NULL DEFAULT 0',
            'list_header_sticky' => 'TINYINT(1) NOT NULL DEFAULT 0',
            'show_preview_link' => 'TINYINT(1) NOT NULL DEFAULT 0',
            'list_last_modification' => 'TINYINT(1) NOT NULL DEFAULT 0',
            'cb_show_author' => 'TINYINT(1) NOT NULL DEFAULT 1',
            'cb_show_top_bar' => 'TINYINT(1) NOT NULL DEFAULT 1',
            'cb_show_bottom_bar' => 'TINYINT(1) NOT NULL DEFAULT 0',
            'cb_show_details_top_bar' => 'TINYINT(1) NOT NULL DEFAULT 1',
            'cb_show_details_bottom_bar' => 'TINYINT(1) NOT NULL DEFAULT 0',
            'show_back_button' => 'TINYINT(1) NOT NULL DEFAULT 1',
            'cb_filter_in_title' => 'TINYINT(1) NOT NULL DEFAULT 0',
            'cb_prefix_in_title' => 'TINYINT(1) NOT NULL DEFAULT 0',
        ], FormDisplayColumnsHelper::requiredColumns());
    }

    public function testAuditReportsMissingColumns(): void
    {
        $db = new FormDisplayColumnsHelperTestDatabase([
            'id' => 'int',
            'name' => 'varchar',
            'cb_show_author' => 'tinyint',
            'cb_show_top_bar' => 'tinyint',
            'cb_show_bottom_bar' => 'tinyint',
            'cb_show_details_top_bar' => 'tinyint',
            'cb_show_details_bottom_bar' => 'tinyint',
            'show_back_button' => 'tinyint',
            'cb_filter_in_title' => 'tinyint',
        ]);

        $summary = FormDisplayColumnsHelper::audit($db);

        self::assertSame(1, $summary['scanned']);
        self::assertSame(1, $summary['missing_tables']);
        self::assertSame(6, $summary['missing_columns_total']);
        self::assertCount(1, $summary['issues']);
        self::assertSame('#__contentbuilderng_forms', $summary['issues'][0]['table']);
        self::assertSame([
            'new_button',
            'button_bar_sticky',
            'list_header_sticky',
            'show_preview_link',
            'list_last_modification',
            'cb_prefix_in_title',
        ], $summary['issues'][0]['missing']);
    }

    public function testRepairAddsMissingColumns(): void
    {
        $db = new FormDisplayColumnsHelperTestDatabase([
            'id' => 'int',
            'name' => 'varchar',
            'new_button' => 'tinyint',
            'button_bar_sticky' => 'tinyint',
            'list_header_sticky' => 'tinyint',
            'show_preview_link' => 'tinyint',
            'list_last_modification' => 'tinyint',
            'cb_show_author' => 'tinyint',
            'cb_show_top_bar' => 'tinyint',
            'cb_show_bottom_bar' => 'tinyint',
            'cb_show_details_top_bar' => 'tinyint',
            'show_back_button' => 'tinyint',
            'cb_filter_in_title' => 'tinyint',
        ]);

        $summary = FormDisplayColumnsHelper::repair($db);

        self::assertSame(1, $summary['scanned']);
        self::assertSame(1, $summary['issues']);
        self::assertSame(2, $summary['repaired']);
        self::assertSame(0, $summary['unchanged']);
        self::assertSame(0, $summary['errors']);
        self::assertSame('repaired', $summary['tables'][0]['status']);
        self::assertSame([
            'cb_show_details_bottom_bar',
            'cb_prefix_in_title',
        ], $summary['tables'][0]['missing']);
        self::assertSame([
            'cb_show_details_bottom_bar',
            'cb_prefix_in_title',
        ], $summary['tables'][0]['added']);
        self::assertCount(2, $db->queries);
        self::assertTrue(
            \in_array(
                'ALTER TABLE `#__contentbuilderng_forms` ADD `cb_prefix_in_title` TINYINT(1) NOT NULL DEFAULT 0',
                $db->queries,
                true
            )
        );
        self::assertTrue(
            \in_array(
                'ALTER TABLE `#__contentbuilderng_forms` ADD `cb_show_details_bottom_bar` TINYINT(1) NOT NULL DEFAULT 0',
                $db->queries,
                true
            )
        );
        self::assertSame(2, $db->executions);
    }
}
