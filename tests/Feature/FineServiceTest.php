<?php

namespace Tests\Feature;

use App\Enums\FineStatus;
use App\Exceptions\LoanException;
use App\Models\Book;
use App\Models\Loan;
use App\Services\FineService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FineServiceTest extends TestCase
{
    use RefreshDatabase;

    private function fines(): FineService
    {
        return app(FineService::class);
    }

    public function test_assessing_a_late_loan_records_the_fine_and_the_suspension(): void
    {
        $user = $this->member();
        $loan = Loan::factory()->create([
            'user_id' => $user->id,
            'book_id' => Book::factory()->withPdf()->create()->id,
            'borrowed_at' => now()->subDays(20),
            'due_at' => now()->subHours(77),
            'fine_rate' => 1000,
        ]);

        $closed = $this->fines()->assess($loan, now());

        $this->assertSame(3, $closed->late_days);
        $this->assertSame(3000, $closed->fine_amount);
        $this->assertSame(FineStatus::Unpaid, $closed->fine_status);
        $this->assertSame(6, (int) round(now()->diffInDays($user->fresh()->suspend_until, true)));
    }

    public function test_assessing_an_on_time_loan_leaves_no_fine_and_no_suspension(): void
    {
        $user = $this->member();
        $loan = Loan::factory()->create([
            'user_id' => $user->id,
            'book_id' => Book::factory()->withPdf()->create()->id,
            'borrowed_at' => now()->subDays(13),
            'due_at' => now()->addDay(),
            'fine_rate' => 1000,
        ]);

        $closed = $this->fines()->assess($loan, now());

        $this->assertSame(0, $closed->late_days);
        $this->assertSame(0, $closed->fine_amount);
        $this->assertSame(FineStatus::None, $closed->fine_status);
        $this->assertNull($user->fresh()->suspend_until);
    }

    public function test_a_settled_fine_cannot_be_settled_twice(): void
    {
        $loan = Loan::factory()->create([
            'book_id' => Book::factory()->withPdf()->create()->id,
            'borrowed_at' => now()->subDays(20),
            'due_at' => now()->subDays(3),
            'fine_rate' => 1000,
            'fine_amount' => 3000,
            'late_days' => 3,
            'fine_paid_at' => now(),
        ]);

        $this->expectException(LoanException::class);
        $this->expectExceptionMessage('Tidak ada denda yang perlu dilunasi.');

        $this->fines()->settle($loan);
    }

    public function test_waiving_a_fine_clears_the_suspension(): void
    {
        $user = $this->member(['suspend_until' => now()->addDays(6)]);
        $admin = $this->admin();
        $loan = Loan::factory()->create([
            'user_id' => $user->id,
            'book_id' => Book::factory()->withPdf()->create()->id,
            'borrowed_at' => now()->subDays(20),
            'due_at' => now()->subDays(3),
            'fine_rate' => 1000,
            'fine_amount' => 3000,
            'late_days' => 3,
        ]);

        $this->fines()->waive($loan, $admin);

        $this->assertSame(FineStatus::Waived, $loan->fresh()->fine_status);
        $this->assertSame($admin->id, $loan->fresh()->fine_waived_by);
        $this->assertNull($user->fresh()->suspend_until);
    }

    public function test_waiving_one_fine_keeps_suspension_from_another_unpaid_fine(): void
    {
        $user = $this->member();
        $admin = $this->admin();
        $bookId = Book::factory()->withPdf()->create()->id;
        $returnedAt = now()->subDays(10);
        $waived = Loan::factory()->create([
            'user_id' => $user->id,
            'book_id' => $bookId,
            'fine_amount' => 3000,
            'late_days' => 3,
        ]);
        Loan::factory()->create([
            'user_id' => $user->id,
            'book_id' => $bookId,
            'returned_at' => $returnedAt,
            'fine_amount' => 2000,
            'late_days' => 2,
        ]);

        $this->fines()->waive($waived, $admin);

        $expected = $returnedAt->copy()->addDays(4);
        $this->assertSame($expected->getTimestamp(), $user->fresh()->suspend_until->getTimestamp());
    }
}
