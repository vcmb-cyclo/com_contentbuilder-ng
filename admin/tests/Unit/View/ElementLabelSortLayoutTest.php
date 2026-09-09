<?php

declare(strict_types=1);

namespace CB\Component\Contentbuilderng\Tests\Unit\View;

use PHPUnit\Framework\TestCase;

final class ElementLabelSortLayoutTest extends TestCase
{
    public function testSortTypeUsesAnIndependentColumnHiddenByDefault(): void
    {
        $root = \dirname(__DIR__, 4);
        $layout = (string) \file_get_contents($root . '/admin/layouts/form/elements_table.php');
        $style = (string) \file_get_contents($root . '/media/css/form-edit.css');
        $script = (string) \file_get_contents($root . '/media/js/form-edit-init.js');

        self::assertStringContainsString('class="cb-item-label-cell"', $layout);
        self::assertStringNotContainsString('cb-item-order-type-details', $layout);
        self::assertStringNotContainsString('cb-item-order-type-trigger', $layout);
        self::assertStringContainsString("'order-type' => Text::_('COM_CONTENTBUILDERNG_ELEMENT_HEADING_ORDER_TYPE')", $layout);
        self::assertStringContainsString("\$defaultHiddenColumns = ['order-type', 'wordwrap'];", $layout);
        self::assertSame(2, substr_count($layout, 'data-cb-col="order-type"'));
        self::assertStringContainsString('cb-item-order-type-select', $layout);
        self::assertStringContainsString(
            '.cb-elements-table th[data-cb-col="order-type"],.cb-elements-table td[data-cb-col="order-type"]{width:10rem;min-width:10rem}',
            $style
        );
        self::assertStringContainsString(
            'th[data-cb-col="export"],.cb-elements-table td[data-cb-col="export"]{width:4.75rem;text-align:center}',
            $style
        );
        self::assertStringContainsString("'order-type': false", $script);
    }
}
