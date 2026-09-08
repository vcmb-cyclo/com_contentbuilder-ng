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
            '.cb-item-label-cell{flex-flow:row wrap;align-items:center;column-gap:.5rem}',
            $style
        );
        self::assertStringContainsString(
            '.cb-item-label-display{flex:0 1 auto;min-width:0;width:auto!important}.cb-item-label-cell>.form-control{flex:1 1 14rem;',
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
    }
}
