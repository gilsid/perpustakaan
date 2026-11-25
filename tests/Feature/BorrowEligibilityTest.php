<?php

namespace Tests\Feature;

use App\Enums\BorrowBlockReason;
use App\Models\Book;
use App\Models\Loan;
use App\Models\User;
use App\Services\BorrowEligibility;
use App\Support\BorrowBlock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BorrowEligibilityTest extends TestCase
{
    use RefreshDatabase;

    private function eligibility(): BorrowEligibility
    {
        return app(BorrowEligibility::class);
    }

    /**
     * @param  list<BorrowBlock>  $blocks
     * @return list<BorrowBlockReason>
     */
    private function reasons(array $blocks): array
    {
        return array_map(fn ($block) => $block->reason, $blocks);
    }

    public function test_a_clear_member_has_no_blocks(): void
    {
        $standing = $this->eligibility()->forMember($this->member());

        $this->assertFalse($standing->isBlocked());
        $this->assertNull($standing->first());
        $this->assertSame(0, $standing->activeLoans);
        $this->assertSame(5, $standing->maxActive);
    }

    public function test_an_inactive_account_cannot_borrow(): void
    {
        $user = User::factory()->inactive()->create();

        $this->assertSame(
            [BorrowBlockReason::InactiveOrNotMember],
            $this->reasons($this->eligibility()->forMember($user)->blocks),
        );
        $this->assertSame('Hanya anggota aktif yang boleh meminjam.', $this->eligibility()->forMember($user)->first()->message());
    }

    public function test_an_admin_cannot_borrow_as_a_member(): void
    {
        $admin = $this->admin();

        $this->assertContains(
            BorrowBlockReason::InactiveOrNotMember,
            $this->reasons($this->eligibility()->forMember($admin)->blocks),
        );
    }

    public function test_account_problems_are_reported_in_order(): void
    {
        $user = $this->member(['suspend_until' => now()->addDays(2)]);
        Loan::factory()->create(['user_id' => $user->id, 'fine_amount' => 2000, 'late_days' => 2]);
        Loan::factory()->expired()->create(['user_id' => $user->id]);

        $this->assertSame(
            [BorrowBlockReason::Suspended, BorrowBlockReason::UnpaidFine, BorrowBlockReason::OverdueLoan],
            $this->reasons($this->eligibility()->forMember($user)->blocks),
        );
    }

    public function test_a_block_carries_the_data_its_message_needs(): void
    {
        $user = $this->member(['suspend_until' => now()->addDays(2)]);
        Loan::factory()->create(['user_id' => $user->id, 'fine_amount' => 3000, 'late_days' => 3]);
        $loan = Loan::factory()->expired()->create(['user_id' => $user->id]);

        $blocks = $this->eligibility()->forMember($user)->blocks;

        $this->assertTrue($blocks[0]->data['until']->isFuture());
        $this->assertSame(3000, $blocks[1]->data['total']);
        $this->assertContains($loan->book->title, $blocks[2]->data['titles']);
    }

    public function test_overdue_titles_are_limited_to_two(): void
    {
        $user = $this->member();
        foreach (range(1, 3) as $ignored) {
            Loan::factory()->expired()->create(['user_id' => $user->id]);
        }

        $this->assertCount(2, $this->eligibility()->forMember($user)->blocks[0]->data['titles']);
    }

    public function test_a_book_without_a_pdf_comes_first(): void
    {
        $user = $this->member(['suspend_until' => now()->addDays(2)]);

        $this->assertSame(
            [BorrowBlockReason::NoPdf, BorrowBlockReason::Suspended],
            $this->reasons($this->eligibility()->forBook($user, Book::factory()->create())->blocks),
        );
    }

    public function test_an_open_loan_on_the_same_book_blocks_a_second_copy(): void
    {
        $user = $this->member();
        $book = Book::factory()->withPdf()->create();
        Loan::factory()->expired()->create(['user_id' => $user->id, 'book_id' => $book->id]);

        $this->assertContains(
            BorrowBlockReason::AlreadyBorrowed,
            $this->reasons($this->eligibility()->forBook($user, $book)->blocks),
        );
    }

    public function test_reaching_the_quota_blocks_another_book(): void
    {
        $user = $this->member();
        foreach (range(1, 5) as $ignored) {
            Loan::factory()->create(['user_id' => $user->id]);
        }

        $book = Book::factory()->withPdf()->create();

        $this->assertSame(
            [BorrowBlockReason::QuotaReached],
            $this->reasons($this->eligibility()->forBook($user, $book)->blocks),
        );
    }

    public function test_borrow_flashes_only_the_first_reason(): void
    {
        $user = $this->member(['suspend_until' => now()->addDay()]);
        Loan::factory()->create(['user_id' => $user->id, 'fine_amount' => 3000, 'late_days' => 3]);
        $book = Book::factory()->withPdf()->create();

        $this->actingAs($user)->post("/buku/{$book->id}/pinjam")
            ->assertSessionHas('error', 'Akun Anda diskors sampai '.$user->suspend_until->translatedFormat('j M Y').' karena keterlambatan pengembalian.')
            ->assertSessionMissing('success');
    }

    public function test_the_shelf_lists_every_reason(): void
    {
        $user = $this->member(['suspend_until' => now()->addDays(2)]);
        Loan::factory()->create(['user_id' => $user->id, 'fine_amount' => 3000, 'late_days' => 3]);
        Loan::factory()->expired()->create(['user_id' => $user->id]);

        $this->actingAs($user)->get('/pinjaman')->assertOk()
            ->assertSee('Akun Anda diskors sampai')
            ->assertSee('Ada denda belum lunas sebesar Rp3.000')
            ->assertSee('Masih ada pinjaman lewat tenggat');
    }
}
