<?php

declare(strict_types=1);

namespace CB\Component\Contentbuilderng\Tests\Unit\Helper;

use CB\Component\Contentbuilderng\Site\Helper\SpreadsheetExportValueHelper;
use PHPUnit\Framework\TestCase;

require_once \dirname(__DIR__, 4) . '/site/src/Helper/SpreadsheetExportValueHelper.php';

final class SpreadsheetExportValueHelperTest extends TestCase
{
    public function testConfiguredSortTypeTakesPriority(): void
    {
        self::assertSame(SpreadsheetExportValueHelper::INTEGER, SpreadsheetExportValueHelper::resolveColumnType(['1', '2'], 'UNSIGNED'));
        self::assertSame(SpreadsheetExportValueHelper::TEXT, SpreadsheetExportValueHelper::resolveColumnType(['1', '2'], 'CHAR'));
        self::assertSame(SpreadsheetExportValueHelper::DATETIME, SpreadsheetExportValueHelper::resolveColumnType(['2026-09-08 10:42'], 'DATETIME'));
    }

    public function testChoiceFieldsUseSafeWholeColumnInference(): void
    {
        self::assertSame(
            SpreadsheetExportValueHelper::INTEGER,
            SpreadsheetExportValueHelper::resolveColumnType(['1', '2', '4'], '', 'Select List')
        );
        self::assertSame(
            SpreadsheetExportValueHelper::TEXT,
            SpreadsheetExportValueHelper::resolveColumnType(['1', 'Détente', '4'], '', 'Radio Group')
        );
        self::assertSame(
            SpreadsheetExportValueHelper::TEXT,
            SpreadsheetExportValueHelper::resolveColumnType(['001', '002', '004'], '', 'Select List')
        );
    }

    public function testInfersOneConsistentTypeForTheWholeColumn(): void
    {
        self::assertSame(SpreadsheetExportValueHelper::INTEGER, SpreadsheetExportValueHelper::resolveColumnType(['1', '2', '10']));
        self::assertSame(SpreadsheetExportValueHelper::DECIMAL, SpreadsheetExportValueHelper::resolveColumnType(['1', '2,5', '10.75']));
        self::assertSame(SpreadsheetExportValueHelper::DATETIME, SpreadsheetExportValueHelper::resolveColumnType(['2025-12-30 10:18', '2026-09-08 10:42']));
        self::assertSame(SpreadsheetExportValueHelper::TEXT, SpreadsheetExportValueHelper::resolveColumnType(['1', 'Détente', '4']));
        self::assertSame(SpreadsheetExportValueHelper::TEXT, SpreadsheetExportValueHelper::resolveColumnType(['0611154258', '33619513139']));
    }

    public function testPreparesSafeTypedValuesAndPreservesInvalidValuesAsText(): void
    {
        self::assertSame(2, SpreadsheetExportValueHelper::prepareCellValue('2', SpreadsheetExportValueHelper::INTEGER)['value']);
        self::assertSame(2.5, SpreadsheetExportValueHelper::prepareCellValue('2,5', SpreadsheetExportValueHelper::DECIMAL)['value']);
        self::assertInstanceOf(\DateTimeImmutable::class, SpreadsheetExportValueHelper::prepareCellValue('2026-09-08', SpreadsheetExportValueHelper::DATE)['value']);

        $formula = SpreadsheetExportValueHelper::prepareCellValue('=1+1', SpreadsheetExportValueHelper::TEXT);
        self::assertSame('=1+1', $formula['value']);
        self::assertSame(SpreadsheetExportValueHelper::TEXT, $formula['type']);

        $numericText = SpreadsheetExportValueHelper::prepareCellValue('00123', SpreadsheetExportValueHelper::TEXT);
        self::assertTrue($numericText['ignoreNumberStoredAsText']);
    }
}
