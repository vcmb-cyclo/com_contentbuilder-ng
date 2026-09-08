<?php

declare(strict_types=1);

namespace CB\Component\Contentbuilderng\Tests\Unit\View;

use PHPUnit\Framework\TestCase;

final class ElementLabelSortLayoutTest extends TestCase
{
    public function testSortTypeIsCollapsedAsAnAdvancedLabelSetting(): void
    {
        $root = \dirname(__DIR__, 4);
        $layout = (string) \file_get_contents($root . '/admin/layouts/form/elements_table.php');
        $style = (string) \file_get_contents($root . '/media/css/form-edit.css');

        self::assertStringContainsString('class="cb-item-label-cell"', $layout);
        self::assertStringContainsString('class="cb-item-order-type-details"', $layout);
        self::assertStringContainsString('class="cb-item-order-type-trigger"', $layout);
        self::assertStringContainsString('cb-item-order-type-select', $layout);
        self::assertStringContainsString(
            '.cb-item-label-cell{flex-flow:row nowrap;align-items:center;column-gap:.25rem}',
            $style
        );
        self::assertStringContainsString(
            '.cb-item-label-display{flex:0 1 auto;min-width:0;width:auto!important;white-space:nowrap}',
            $style
        );
        self::assertStringContainsString(
            '.cb-item-order-type-details{flex:0 0 auto;align-self:center}',
            $style
        );
        self::assertStringContainsString(
            '.cb-item-order-type-details[open]{flex-basis:100%}',
            $style
        );
        self::assertStringContainsString(
            '.cb-item-order-type-trigger{display:inline-flex;align-items:center;justify-content:center;gap:.2rem;width:1.4rem;height:1.4rem;',
            $style
        );
        self::assertStringContainsString(
            'th[data-cb-col="export"],.cb-elements-table td[data-cb-col="export"]{width:4.75rem;text-align:center}',
            $style
        );
    }
}
