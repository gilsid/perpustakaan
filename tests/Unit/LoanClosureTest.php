<?php

namespace Tests\Unit;

use App\Models\Loan;
use App\Support\LoanClosure;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class LoanClosureTest extends TestCase
{
    private function closure(array $attributes = [], ?CarbonImmutable $suspendedUntil = null): LoanClosure
    {
        return new LoanClosure(new Loan($attributes), $suspendedUntil);
    }

    public function test_an_on_time_return_says_just_that(): void
    {
        $this->assertSame('Buku dikembalikan.', $this->closure()->memberMessage());
    }

    public function test_a_late_return_names_the_fine_and_the_late_days(): void
    {
        $this->assertSame(
            'Buku dikembalikan. Denda Rp3.000 (3 hari telat).',
            $this->closure(['late_days' => 3, 'fine_amount' => 3000])->memberMessage(),
        );
    }

    public function test_a_late_return_with_a_suspension_names_the_end_date(): void
    {
        $message = $this->closure(
            ['late_days' => 3, 'fine_amount' => 3000],
            CarbonImmutable::parse('2026-12-01 10:00:00')->locale('id'),
        )->memberMessage();

        $this->assertSame('Buku dikembalikan. Denda Rp3.000 (3 hari telat). Akun diskors sampai 1 Des 2026.', $message);
    }

    public function test_a_zero_fine_still_reports_the_suspension(): void
    {
        $message = $this->closure(
            ['late_days' => 2, 'fine_amount' => 0],
            CarbonImmutable::parse('2026-12-01 10:00:00'),
        )->memberMessage();

        $this->assertStringNotContainsString('Denda', $message);
        $this->assertStringContainsString('Akun diskors sampai', $message);
    }

    public function test_a_past_suspension_is_not_announced(): void
    {
        $message = $this->closure(
            ['late_days' => 3, 'fine_amount' => 3000],
            CarbonImmutable::parse('2020-01-01 10:00:00'),
        )->memberMessage();

        $this->assertSame('Buku dikembalikan. Denda Rp3.000 (3 hari telat).', $message);
    }

    public function test_the_admin_message_reports_the_recorded_fine(): void
    {
        $this->assertSame('Pinjaman diakhiri.', $this->closure()->adminMessage());
        $this->assertSame(
            'Pinjaman diakhiri. Denda Rp3.000 (3 hari telat) dicatat.',
            $this->closure(['late_days' => 3, 'fine_amount' => 3000])->adminMessage(),
        );
    }
}
