<?php

namespace Tests\Unit;

use App\Enums\FineStatus;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class FineStatusTest extends TestCase
{
    public function test_no_amount_means_no_fine(): void
    {
        $this->assertSame(FineStatus::None, FineStatus::derive(0, null, null));
    }

    public function test_an_unsettled_amount_is_unpaid(): void
    {
        $this->assertSame(FineStatus::Unpaid, FineStatus::derive(3000, null, null));
    }

    public function test_a_paid_amount_is_paid(): void
    {
        $this->assertSame(FineStatus::Paid, FineStatus::derive(3000, CarbonImmutable::now(), null));
    }

    public function test_a_waived_amount_is_waived_even_if_it_was_paid_before(): void
    {
        $now = CarbonImmutable::now();

        $this->assertSame(FineStatus::Waived, FineStatus::derive(3000, $now, $now));
    }

    public function test_only_an_unpaid_fine_can_be_settled_or_waived(): void
    {
        foreach ([FineStatus::None, FineStatus::Paid, FineStatus::Waived] as $status) {
            $this->assertFalse($status->canBeSettled());
            $this->assertFalse($status->canBeWaived());
        }

        $this->assertTrue(FineStatus::Unpaid->canBeSettled());
        $this->assertTrue(FineStatus::Unpaid->canBeWaived());
    }
}
