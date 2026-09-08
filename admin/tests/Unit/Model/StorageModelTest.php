<?php

declare(strict_types=1);

namespace CB\Component\Contentbuilderng\Tests\Unit\Model;

use CB\Component\Contentbuilderng\Administrator\Model\StorageModel;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class StorageModelTest extends TestCase
{
    private StorageModel $model;

    protected function setUp(): void
    {
        $reflection = new \ReflectionClass(StorageModel::class);
        $this->model = $reflection->newInstanceWithoutConstructor();
    }

    #[DataProvider('normalizeFieldIdentifierProvider')]
    public function testNormalizeFieldIdentifier(string $input, string $expected): void
    {
        $result = $this->invokePrivateMethod('normalizeFieldIdentifier', $input);

        self::assertSame($expected, $result);
    }

    public static function normalizeFieldIdentifierProvider(): array
    {
        return [
            'french accent' => ['Prénom', 'Prenom'],
            'latin1 french accent' => ["Pr\xE9nom", 'Prenom'],
            'ligature and symbol' => ['cœur & âme', 'coeur_ame'],
            'german sharp s' => ['Straße', 'Strasse'],
            'turkish dotted i' => ['İsim', 'Isim'],
            'leading digit' => ['123 titre', 'field_123_titre'],
            'spaces and separators' => ['  hello---world  ', 'hello_world'],
            'only symbols' => ['***', ''],
        ];
    }

    public function testKnownExternalTableSynchronizationIsReadOnly(): void
    {
        $method = new \ReflectionMethod(StorageModel::class, 'syncStorageDataTableOrBytable');
        $source = file(
            (string) $method->getFileName(),
            FILE_IGNORE_NEW_LINES
        );
        self::assertIsArray($source);

        $methodSource = implode("\n", array_slice(
            $source,
            $method->getStartLine() - 1,
            $method->getEndLine() - $method->getStartLine() + 1
        ));
        $externalMarker = '// BYTABLE = table externe';
        $externalOffset = strpos($methodSource, $externalMarker);
        self::assertNotFalse($externalOffset);

        $externalSource = substr($methodSource, $externalOffset);
        self::assertStringContainsString('if ($bytable === 1)', $externalSource);

        $readOnlyMarker = '// bytable=2 ne fait que lire';
        $readOnlyOffset = strpos($externalSource, $readOnlyMarker);
        self::assertNotFalse($readOnlyOffset);
        $readOnlySource = substr($externalSource, $readOnlyOffset);

        self::assertStringNotContainsString('ALTER TABLE', $readOnlySource);
        self::assertStringNotContainsString('->update($db->quoteName($name))', $readOnlySource);
        self::assertStringContainsString("->select(\$db->quoteName('id'))", $readOnlySource);
    }

    private function invokePrivateMethod(string $method, ...$args)
    {
        $reflection = new \ReflectionClass($this->model);
        $target = $reflection->getMethod($method);

        return $target->invoke($this->model, ...$args);
    }
}
