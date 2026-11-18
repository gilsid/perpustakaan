<?php

namespace Tests\Unit;

use App\Services\FineCalculator;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class FineCalculatorTest extends TestCase
{
    private FineCalculator $calc;

    protected function setUp(): void
    {
        parent::setUp();

        // Tarif tidak ikut dihitung di sini: tarif adalah snapshot per pinjaman.
        $this->calc = new FineCalculator(graceHours: 24, maxAmount: 50000);
    }

    private function due(): CarbonImmutable
    {
        return CarbonImmutable::parse('2026-03-10 10:00:00', 'Asia/Jakarta');
    }

    public function test_returning_before_the_due_date_is_free(): void
    {
        $this->assertSame(0, $this->calc->lateDays($this->due(), $this->due()->subHour()));
    }

    public function test_returning_within_the_grace_period_is_free(): void
    {
        $this->assertSame(0, $this->calc->lateDays($this->due(), $this->due()->addHours(23)));
    }

    public function test_the_grace_boundary_itself_is_still_free(): void
    {
        $this->assertSame(0, $this->calc->lateDays($this->due(), $this->due()->addDay()));
    }

    public function test_one_second_past_the_grace_period_counts_as_one_day(): void
    {
        $this->assertSame(1, $this->calc->lateDays($this->due(), $this->due()->addDay()->addSecond()));
        $this->assertSame(1000, $this->calc->amount(1, 1000));
    }

    public function test_partial_days_round_up(): void
    {
        $this->assertSame(2, $this->calc->lateDays($this->due(), $this->due()->addHours(49)));
    }

    public function test_the_amount_is_capped(): void
    {
        $this->assertSame(50000, $this->calc->amount(100, 1000));
    }

    public function test_the_amount_uses_the_given_rate(): void
    {
        $this->assertSame(1500, $this->calc->amount(3, 500));
    }

    public function test_zero_or_negative_values_never_produce_a_fine(): void
    {
        $this->assertSame(0, $this->calc->amount(0, 1000));
        $this->assertSame(0, $this->calc->amount(-3, 1000));
        $this->assertSame(0, $this->calc->amount(3, 0));
    }
}
