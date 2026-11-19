<?php

namespace Tests\Unit;

use App\Services\FineCalculator;
use PHPUnit\Framework\TestCase;

class FineCalculatorTest extends TestCase
{
    private FineCalculator $calc;

    protected function setUp(): void
    {
        parent::setUp();

        // Tarif tidak ikut dihitung di sini: tarif adalah snapshot per pinjaman.
        $this->calc = new FineCalculator(maxAmount: 50000);
    }

    public function test_the_amount_is_capped(): void
    {
        $this->assertSame(50000, $this->calc->amount(100, 1000));
    }

    public function test_the_amount_uses_the_given_rate(): void
    {
        $this->assertSame(1500, $this->calc->amount(3, 500));
    }

    public function test_a_single_late_day_uses_the_rate_once(): void
    {
        $this->assertSame(1000, $this->calc->amount(1, 1000));
    }

    public function test_zero_or_negative_values_never_produce_a_fine(): void
    {
        $this->assertSame(0, $this->calc->amount(0, 1000));
        $this->assertSame(0, $this->calc->amount(-3, 1000));
        $this->assertSame(0, $this->calc->amount(3, 0));
    }
}
