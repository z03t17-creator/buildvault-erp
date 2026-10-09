<?php

namespace Tests\Unit;

use App\Services\RetentionMathService;
use Carbon\Carbon;
use Tests\TestCase;

class RetentionMathServiceTest extends TestCase
{
    private RetentionMathService $math;

    protected function setUp(): void
    {
        parent::setUp();
        $this->math = new RetentionMathService;
    }

    public function test_client_advance_retention_isolates_currencies(): void
    {
        // Deliberately mismatched legs (not FX-related) to prove independent math.
        $result = $this->math->clientAdvanceRetention(
            1000,
            2_000_000,
            '2026-01-01',
        );

        $this->assertSame(10.0, $result['hold_pct']);
        $this->assertSame(180, $result['maturity_days']);
        $this->assertSame('2026-06-30', $result['maturity_date']);
        $this->assertSame(100.0, $result['retention_usd']);
        $this->assertSame(200000.0, $result['retention_iqd']);
        $this->assertSame(900.0, $result['net_after_retention_usd']);
        $this->assertSame(1_800_000.0, $result['net_after_retention_iqd']);
        // Never blend: USD retention × any rate ≠ IQD retention for mismatched legs
        $this->assertNotEquals($result['retention_usd'] * 1310, $result['retention_iqd']);
    }

    public function test_staff_work_pay_retention_stub(): void
    {
        $result = $this->math->staffWorkPayRetention(500, 0, Carbon::parse('2026-03-15'));

        $this->assertSame('staff', $result['layer']);
        $this->assertSame(50.0, $result['retention_usd']);
        $this->assertSame(0.0, $result['retention_iqd']);
        $this->assertSame('2026-09-11', $result['maturity_date']);
    }

    public function test_staff_net_payable_deducts_advances_and_penalties_per_currency(): void
    {
        $net = $this->math->staffNetPayable(
            grossUsd: 1000,
            grossIqd: 2000000,
            retentionUsd: 100,
            retentionIqd: 0,
            advancesUsd: 50,
            advancesIqd: 100000,
            penaltiesUsd: 0,
            penaltiesIqd: 25000,
        );

        $this->assertSame(850.0, $net['net_usd']);
        $this->assertSame(1_875_000.0, $net['net_iqd']);
    }

    public function test_employee_salary_has_no_automatic_retention(): void
    {
        $this->assertFalse($this->math->employeeSalaryHasRetention());
    }

    public function test_usd_only_gross_leaves_iqd_zero(): void
    {
        $result = $this->math->clientAdvanceRetention(250, 0);

        $this->assertSame(25.0, $result['retention_usd']);
        $this->assertSame(0.0, $result['retention_iqd']);
    }
}
