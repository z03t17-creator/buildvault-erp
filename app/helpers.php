<?php

use App\Support\NumberFormat;

if (! function_exists('format_number')) {
    /**
     * Format a number with thousand separators (commas).
     */
    function format_number(mixed $value, int $decimals = 0): string
    {
        return NumberFormat::number($value, $decimals);
    }
}

if (! function_exists('format_iqd')) {
    /**
     * Format an IQD amount with thousand separators and optional label.
     */
    function format_iqd(mixed $value, ?string $label = 'IQD', int $decimals = 0): string
    {
        return NumberFormat::iqd($value, $label, $decimals);
    }
}

if (! function_exists('parse_number')) {
    /**
     * Parse a formatted number string to float.
     */
    function parse_number(mixed $value): float
    {
        return NumberFormat::parse($value);
    }
}
