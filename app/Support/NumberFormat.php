<?php

namespace App\Support;

/**
 * Shared IQD / amount formatting for Blade, controllers, and messages.
 * Uses en-US-style thousand separators (commas); no "$".
 */
final class NumberFormat
{
    /**
     * Format a numeric value with thousand separators.
     * Integers by default; pass $decimals when fractional amounts are used.
     */
    public static function number(mixed $value, int $decimals = 0): string
    {
        if ($value === null || $value === '') {
            return number_format(0, $decimals, '.', ',');
        }

        return number_format((float) $value, $decimals, '.', ',');
    }

    /**
     * Format an IQD amount: "71,555,689 IQD" (label optional / translatable).
     */
    public static function iqd(mixed $value, ?string $label = 'IQD', int $decimals = 0): string
    {
        $formatted = self::number($value, $decimals);

        if ($label === null || $label === '') {
            return $formatted;
        }

        return $formatted.' '.$label;
    }

    /**
     * Strip grouping separators and parse to float (empty → 0).
     */
    public static function parse(mixed $value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }

        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        $raw = preg_replace('/[^\d.\-]/', '', str_replace(',', '', (string) $value));

        if ($raw === null || $raw === '' || $raw === '-' || $raw === '.') {
            return 0.0;
        }

        return (float) $raw;
    }
}
