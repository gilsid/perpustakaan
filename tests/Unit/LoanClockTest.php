<?php

namespace Tests\Unit;

use App\Services\LoanClock;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class LoanClockTest extends TestCase
{
    private LoanClock $clock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->clock = new LoanClock(graceHours: 24, dueSoonDays: 3);
    }

    private function due(): CarbonImmutable
    {
        return CarbonImmutable::parse('2026-03-10 10:00:00', 'Asia/Jakarta');
    }

    public function test_the_due_moment_itself_is_not_overdue(): void
    {
        $this->assertFalse($this->clock->isOverdue($this->due(), $this->due()));
        $this->assertTrue($this->clock->isOverdue($this->due(), $this->due()->addSecond()));
    }

    public function test_returning_before_the_due_date_is_free(): void
    {
        $this->assertSame(0, $this->clock->lateDays($this->due(), $this->due()->subHour()));
    }

    public function test_returning_within_the_grace_period_is_free(): void
    {
        $this->assertSame(0, $this->clock->lateDays($this->due(), $this->due()->addHours(23)));
    }

    public function test_the_grace_boundary_itself_is_still_free(): void
    {
        $this->assertSame(0, $this->clock->lateDays($this->due(), $this->due()->addDay()));
    }

    public function test_the_grace_end_is_the_moment_fines_start(): void
    {
        $this->assertSame($this->due()->addDay()->toIso8601String(), $this->clock->graceEndsAt($this->due())->toIso8601String());
    }

    public function test_one_second_past_the_grace_period_counts_as_one_day(): void
    {
        $this->assertSame(1, $this->clock->lateDays($this->due(), $this->due()->addDay()->addSecond()));
    }

    public function test_partial_days_after_grace_round_up(): void
    {
        $this->assertSame(2, $this->clock->lateDays($this->due(), $this->due()->addHours(49)));
    }

    public function test_seventy_seven_hours_late_is_three_days_after_grace(): void
    {
        $this->assertSame(3, $this->clock->lateDays($this->due(), $this->due()->addHours(77)));
    }

    public function test_days_left_rounds_up_and_is_never_positive_after_the_due_date(): void
    {
        $this->assertSame(2, $this->clock->daysLeft($this->due(), $this->due()->subHours(25)));
        $this->assertSame(1, $this->clock->daysLeft($this->due(), $this->due()->subHour()));
        $this->assertSame(0, $this->clock->daysLeft($this->due(), $this->due()->addHour()));
        $this->assertSame(-2, $this->clock->daysLeft($this->due(), $this->due()->addDays(2)));
    }

    public function test_a_loan_is_due_soon_from_the_configured_threshold(): void
    {
        $this->assertFalse($this->clock->isDueSoon($this->due(), $this->due()->subDays(4)));
        $this->assertTrue($this->clock->isDueSoon($this->due(), $this->due()->subDays(3)));
        $this->assertTrue($this->clock->isDueSoon($this->due(), $this->due()->subHour()));
    }

    public function test_the_due_soon_cutoff_is_the_threshold_in_the_future(): void
    {
        $this->assertSame(
            $this->due()->addDays(3)->toIso8601String(),
            $this->clock->dueSoonCutoff($this->due())->toIso8601String(),
        );
    }
}
