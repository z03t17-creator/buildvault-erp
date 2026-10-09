<?php

namespace Tests\Unit;

use App\Services\ProductionRecordService;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ProductionRecordServiceTest extends TestCase
{
    private ProductionRecordService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ProductionRecordService;
    }

    #[DataProvider('mathCases')]
    public function test_remaining_and_progress_math(
        float $assigned,
        float $completed,
        float $expectedRemaining,
        float $expectedProgress,
    ): void {
        $result = $this->service->calculate($assigned, $completed);

        $this->assertSame($expectedRemaining, $result['remaining']);
        $this->assertSame($expectedProgress, $result['progress_pct']);
    }

    /**
     * @return list<array{0: float, 1: float, 2: float, 3: float}>
     */
    public static function mathCases(): array
    {
        return [
            'partial progress' => [12.0, 5.0, 7.0, 41.67],
            'complete' => [8.0, 8.0, 0.0, 100.0],
            'none done' => [10.0, 0.0, 10.0, 0.0],
            'zero assigned' => [0.0, 0.0, 0.0, 0.0],
            'decimals' => [3.5, 1.25, 2.25, 35.71],
        ];
    }

    public function test_completed_cannot_exceed_assigned(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->service->calculate(5, 6);
    }

    public function test_negative_assigned_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->service->calculate(-1, 0);
    }
}
