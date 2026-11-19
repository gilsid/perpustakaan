<?php

namespace Tests\Unit;

use App\Services\FinePolicy;
use PHPUnit\Framework\TestCase;

class FinePolicyTest extends TestCase
{
    private FinePolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->policy = new FinePolicy(
            ratePerDay: 1000,
            maxAmount: 50000,
            suspendFactor: 2,
            suspendMaxDays: 30,
        );
    }

    public function test_the_configured_rate_is_the_snapshot_for_new_loans(): void
    {
        $this->assertSame(1000, $this->policy->ratePerDay());
    }

    public function test_the_amount_uses_the_snapshot_rate(): void
    {
        $this->assertSame(1500, $this->policy->amount(3, 500));
    }

    public function test_the_amount_is_capped(): void
    {
        $this->assertSame(50000, $this->policy->amount(100, 1000));
    }

    public function test_zero_or_negative_values_never_produce_a_fine(): void
    {
        $this->assertSame(0, $this->policy->amount(0, 1000));
        $this->assertSame(0, $this->policy->amount(-3, 1000));
        $this->assertSame(0, $this->policy->amount(3, 0));
    }

    public function test_suspension_days_follow_the_late_days(): void
    {
        $this->assertSame(0, $this->policy->suspensionDays(0));
        $this->assertSame(2, $this->policy->suspensionDays(1));
        $this->assertSame(6, $this->policy->suspensionDays(3));
    }

    public function test_suspension_days_are_capped(): void
    {
        $this->assertSame(30, $this->policy->suspensionDays(20));
    }
}
