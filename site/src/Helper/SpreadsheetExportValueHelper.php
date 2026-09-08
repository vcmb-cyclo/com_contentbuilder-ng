<?php

declare(strict_types=1);

namespace CB\Component\Contentbuilderng\Site\Helper;

\defined('_JEXEC') or die;

final class SpreadsheetExportValueHelper
{
    public const TEXT = 'text';
    public const INTEGER = 'integer';
    public const DECIMAL = 'decimal';
    public const DATE = 'date';
    public const DATETIME = 'datetime';
    public const TIME = 'time';

    /** @param list<mixed> $values */
    public static function resolveColumnType(array $values, ?string $orderType = null, ?string $sourceType = null): string
    {
        $configuredType = match (strtoupper(trim((string) $orderType))) {
            'UNSIGNED' => self::INTEGER,
            'DECIMAL' => self::DECIMAL,
            'DATE' => self::DATE,
            'DATETIME' => self::DATETIME,
            'TIME' => self::TIME,
            'CHAR' => self::TEXT,
            default => null,
        };
        if ($configuredType !== null) {
            return $configuredType;
        }

        $normalizedSourceType = strtolower(trim((string) $sourceType));
        $sourceTypeMap = [
            'number' => self::INTEGER,
            'int' => self::INTEGER,
            'integer' => self::INTEGER,
            'boolean' => self::INTEGER,
            'decimal' => self::DECIMAL,
            'calendar' => self::DATE,
            'date' => self::DATE,
            'datetime' => self::DATETIME,
            'time' => self::TIME,
        ];
        if (isset($sourceTypeMap[$normalizedSourceType])) {
            return $sourceTypeMap[$normalizedSourceType];
        }

        $nonEmptyValues = [];
        foreach ($values as $value) {
            if (!is_scalar($value) && $value !== null) {
                return self::TEXT;
            }
            $value = trim((string) $value);
            if ($value !== '') {
                $nonEmptyValues[] = $value;
            }
        }
        if ($nonEmptyValues === []) {
            return self::TEXT;
        }

        if (self::allTemporalValues($nonEmptyValues, self::DATETIME)) {
            return self::DATETIME;
        }
        if (self::allTemporalValues($nonEmptyValues, self::DATE)) {
            return self::DATE;
        }
        if (self::allTemporalValues($nonEmptyValues, self::TIME)) {
            return self::TIME;
        }

        $allIntegers = true;
        foreach ($nonEmptyValues as $value) {
            if (!preg_match('/^-?(?:0|[1-9]\d{0,9})$/D', $value)) {
                $allIntegers = false;
                break;
            }
        }
        if ($allIntegers) {
            return self::INTEGER;
        }

        $hasFraction = false;
        foreach ($nonEmptyValues as $value) {
            if (!preg_match('/^-?(?:0|[1-9]\d{0,9})(?:[.,]\d+)?$/D', $value)) {
                return self::TEXT;
            }
            $hasFraction = $hasFraction || str_contains($value, '.') || str_contains($value, ',');
        }

        return $hasFraction ? self::DECIMAL : self::TEXT;
    }

    /** @return array{value:string|int|float|\DateTimeImmutable,type:string,ignoreNumberStoredAsText:bool} */
    public static function prepareCellValue(mixed $value, string $columnType): array
    {
        $value = is_scalar($value) ? trim((string) $value) : '';
        if ($value === '') {
            return ['value' => '', 'type' => self::TEXT, 'ignoreNumberStoredAsText' => false];
        }

        if ($columnType === self::INTEGER && preg_match('/^[+-]?\d+$/D', $value)) {
            $digits = ltrim($value, '+-0');
            if (strlen($digits === '' ? '0' : $digits) <= 15) {
                return ['value' => (int) $value, 'type' => self::INTEGER, 'ignoreNumberStoredAsText' => false];
            }
        }

        if ($columnType === self::DECIMAL && preg_match('/^[+-]?\d+(?:[.,]\d+)?$/D', $value)) {
            $number = (float) str_replace(',', '.', $value);
            if (is_finite($number)) {
                return ['value' => $number, 'type' => self::DECIMAL, 'ignoreNumberStoredAsText' => false];
            }
        }

        if (in_array($columnType, [self::DATE, self::DATETIME, self::TIME], true)) {
            $date = self::parseTemporalValue($value, $columnType);
            if ($date !== null) {
                return ['value' => $date, 'type' => $columnType, 'ignoreNumberStoredAsText' => false];
            }
        }

        return [
            'value' => $value,
            'type' => self::TEXT,
            'ignoreNumberStoredAsText' => is_numeric($value),
        ];
    }

    public static function numberFormat(string $columnType): ?string
    {
        return match ($columnType) {
            self::INTEGER => '#,##0',
            self::DECIMAL => '#,##0.###############',
            self::DATE => 'yyyy-mm-dd',
            self::DATETIME => 'yyyy-mm-dd hh:mm',
            self::TIME => 'hh:mm:ss',
            default => null,
        };
    }

    /** @param list<string> $values */
    private static function allTemporalValues(array $values, string $type): bool
    {
        foreach ($values as $value) {
            if (self::parseTemporalValue($value, $type) === null) {
                return false;
            }
        }

        return true;
    }

    private static function parseTemporalValue(string $value, string $type): ?\DateTimeImmutable
    {
        $formats = match ($type) {
            self::DATE => ['Y-m-d'],
            self::DATETIME => ['Y-m-d H:i:s', 'Y-m-d H:i'],
            self::TIME => ['H:i:s', 'H:i'],
            default => [],
        };
        foreach ($formats as $format) {
            $date = \DateTimeImmutable::createFromFormat('!' . $format, $value);
            $errors = \DateTimeImmutable::getLastErrors();
            if (
                $date instanceof \DateTimeImmutable
                && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))
                && $date->format($format) === $value
            ) {
                return $date;
            }
        }

        return null;
    }
}
