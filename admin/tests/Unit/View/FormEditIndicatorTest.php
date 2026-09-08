<?php

declare(strict_types=1);

namespace CB\Component\Contentbuilderng\Tests\Unit\View;

use CB\Component\Contentbuilderng\Administrator\Service\FormAuditService;
use Joomla\CMS\Language\Text;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 3) . '/src/Service/FormAuditService.php';

final class FormEditIndicatorTest extends TestCase
{
    public function testMissingPublishedStateUsesRedBeforeOrangeWhenAStateFunctionIsEnabled(): void
    {
        $source = (string) file_get_contents(dirname(__DIR__, 4) . '/admin/tmpl/form/edit.php');
        $redPriority = strpos($source, '} elseif ($showsListStates || $hasListStatePermission) {');
        $orangeFallback = strpos($source, "COM_CONTENTBUILDERNG_TAB_TIP_LIST_STATES_ORANGE_FILTER_NO_STATE", $redPriority ?: 0);

        self::assertNotFalse($redPriority);
        self::assertNotFalse($orangeFallback);
        self::assertLessThan($orangeFallback, $redPriority);
    }

    public static function scenarios(): array
    {
        return [
            'new only' => [false, true, true, true, false, 'INACTIVE_EMPTY'],
            'edit active' => [true, true, true, true, false, 'ACTIVE'],
            'edit through details with list button disabled' => [true, false, true, true, false, 'ACTIVE'],
            'no edit entry point' => [true, false, true, true, false, 'INACTIVE_EMPTY', false],
            'list edit without detail access' => [true, true, true, true, false, 'ACTIVE', false],
            'details edit missing template' => [true, false, true, false, false, 'INVALID'],
            'details edit without editable fields' => [true, false, false, true, false, 'INCOMPLETE'],
            'no editable fields' => [true, true, false, true, false, 'INCOMPLETE'],
            'missing template' => [true, true, true, false, false, 'INVALID'],
            'invalid template' => [true, true, true, true, true, 'INVALID'],
            'disabled with audit issue' => [false, false, true, true, true, 'INACTIVE_EMPTY'],
        ];
    }

    #[DataProvider('scenarios')]
    public function testEditStateIsIndependentOfCreation(
        bool $edit,
        bool $button,
        bool $fields,
        bool $template,
        bool $auditIssue,
        string $expected,
        bool $detailAccess = true
    ): void {
        $source = file_get_contents(dirname(__DIR__, 4) . '/admin/tmpl/form/edit.php');
        $start = strpos($source, '$frontendPermissionConfig =');
        $end = strpos($source, '$buildTemplateTabTip =', $start);
        self::assertNotFalse($start);
        self::assertNotFalse($end);
        $code = substr($source, $start, $end - $start);
        // Execute the actual indicator calculation, including its badge rendering.
        $code = 'use ' . FormAuditService::class . '; use ' . Text::class . '; ' . $code
            . ' return [$editableTemplateState, $detailsTemplateState];';

        foreach ([false, true] as $new) {
            foreach ([false, true] as $locked) {
                $view = new class {
                    public object $item;
                    public array $all_elements;
                    public array $audit;

                    public function states(string $code): array
                    {
                        return eval($code);
                    }
                };
                $view->item = (object) [
                    'config' => ['permissions_fe' => [['edit' => $edit, 'new' => $new, 'view' => $detailAccess]]],
                    'edit_button' => $button,
                    'new_button' => $new,
                    'editable_template' => $template ? '{name:item}' : '',
                    'editable_template_locked' => $locked,
                    'details_template' => '{name:value}',
                ];
                $view->all_elements = [(object) [
                    'published' => 1, 'editable' => $fields, 'detail_include' => 1, 'linkable' => 1,
                ]];
                $view->audit = ['checks' => $auditIssue ? [[
                    'status' => FormAuditService::STATUS_ERROR,
                    'reference' => 'CBNG-AUDIT-UNKNOWN-MARKER-EDIT',
                ]] : []];
                [$editState, $detailState] = $view->states($code);
                self::assertSame('COM_CONTENTBUILDERNG_TAB_TEMPLATE_STATUS_' . $expected, $editState['tipKey']);
                self::assertSame($locked, $editState['locked']);
                self::assertSame('COM_CONTENTBUILDERNG_TAB_TEMPLATE_STATUS_' . ($detailAccess ? 'ACTIVE' : 'INACTIVE_EMPTY'), $detailState['tipKey']);
                if ($expected === 'INACTIVE_EMPTY') {
                    self::assertStringNotContainsString('is-filled', $editState['badge']);
                    self::assertSame($locked, str_contains($editState['badge'], 'is-locked'));
                }
            }
        }
    }
}
