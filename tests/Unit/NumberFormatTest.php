<?php

namespace Tests\Unit;

use App\Support\NumberFormat;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class NumberFormatTest extends TestCase
{
    public function test_formats_integers_with_commas(): void
    {
        $this->assertSame('71,555,689', NumberFormat::number(71555689));
        $this->assertSame('1,000', NumberFormat::number(1000));
        $this->assertSame('0', NumberFormat::number(0));
        $this->assertSame('0', NumberFormat::number(null));
    }

    public function test_formats_iqd_with_label(): void
    {
        $this->assertSame('71,555,689 IQD', NumberFormat::iqd(71555689));
        $this->assertSame('1,310,000 دینار', NumberFormat::iqd(1_310_000, 'دینار'));
        $this->assertSame('500', NumberFormat::iqd(500, null));
        $this->assertSame('500', NumberFormat::iqd(500, ''));
    }

    public function test_formats_decimals_when_requested(): void
    {
        $this->assertSame('1,310.50', NumberFormat::number(1310.5, 2));
        $this->assertSame('1,310.50 IQD', NumberFormat::iqd(1310.5, 'IQD', 2));
    }

    #[DataProvider('parseProvider')]
    public function test_parse_strips_commas(mixed $input, float $expected): void
    {
        $this->assertSame($expected, NumberFormat::parse($input));
    }

    public static function parseProvider(): array
    {
        return [
            'plain' => ['71555689', 71555689.0],
            'commas' => ['71,555,689', 71555689.0],
            'spaces junk' => ['71,555,689 IQD', 71555689.0],
            'float' => [1310.5, 1310.5],
            'empty' => ['', 0.0],
            'null' => [null, 0.0],
            'negative' => ['-1,250', -1250.0],
        ];
    }

    public function test_helper_functions_are_available(): void
    {
        require_once dirname(__DIR__, 2).'/app/helpers.php';

        $this->assertSame('71,555,689', format_number(71555689));
        $this->assertSame('71,555,689 IQD', format_iqd(71555689));
        $this->assertSame(71555689.0, parse_number('71,555,689'));
    }
}
