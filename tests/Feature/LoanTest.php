<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Loan;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoanTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_can_borrow_a_digital_book(): void
    {
        $user = $this->member();
        $book = Book::factory()->withPdf()->create();

        $this->actingAs($user)->post("/buku/{$book->id}/pinjam")
            ->assertRedirect(route('books.show', $book))
            ->assertSessionHas('success');

        $loan = Loan::firstWhere(['user_id' => $user->id, 'book_id' => $book->id]);
        $this->assertTrue($loan->due_at->isFuture());
        $this->assertEqualsWithDelta(14, now()->diffInDays($loan->due_at, true), 0.1);
    }

    public function test_double_borrowing_is_rejected(): void
    {
        $user = $this->member();
        $book = Book::factory()->withPdf()->create();

        $this->actingAs($user)->post("/buku/{$book->id}/pinjam");
        $this->post("/buku/{$book->id}/pinjam")->assertSessionHas('error', 'Anda sudah meminjam buku ini.');

        $this->assertSame(1, Loan::count());
    }

    public function test_books_without_pdf_cannot_be_borrowed(): void
    {
        $book = Book::factory()->create();

        $this->actingAs($this->member())->post("/buku/{$book->id}/pinjam")
            ->assertSessionHas('error', 'Buku ini belum tersedia dalam bentuk digital, jadi belum bisa dipinjam.');
        $this->assertSame(0, Loan::count());
    }

    public function test_active_loan_limit_is_enforced(): void
    {
        $user = $this->member();
        $books = Book::factory()->withPdf()->count(6)->create();

        foreach ($books as $book) {
            $this->actingAs($user)->post("/buku/{$book->id}/pinjam");
        }

        $this->assertSame(5, Loan::where('user_id', $user->id)->count());
        $this->post("/buku/{$books[5]->id}/pinjam")->assertSessionHas('error');
    }

    public function test_returning_frees_a_slot_and_allows_borrowing_again(): void
    {
        $user = $this->member();
        $book = Book::factory()->withPdf()->create();

        $this->actingAs($user)->post("/buku/{$book->id}/pinjam");
        $this->delete("/buku/{$book->id}/pinjam")->assertSessionHas('success', 'Buku dikembalikan.');
        $this->assertNotNull(Loan::first()->returned_at);

        $this->post("/buku/{$book->id}/pinjam")->assertSessionHas('success');
        $this->assertSame(2, Loan::count());
    }

    public function test_an_overdue_loan_stays_open_and_blocks_borrowing(): void
    {
        $user = $this->member();
        $book = Book::factory()->withPdf()->create();
        $loan = Loan::factory()->expired()->create(['user_id' => $user->id, 'book_id' => $book->id]);

        $this->actingAs($user)->post("/buku/{$book->id}/pinjam")->assertSessionHas('error');

        $this->assertNull($loan->fresh()->returned_at, 'Pinjaman telat tidak boleh ditutup otomatis.');
        $this->assertSame(0, Loan::where('user_id', $user->id)->active()->count());
    }

    public function test_overdue_loans_block_other_borrows_too(): void
    {
        $user = $this->member();
        Loan::factory()->expired()->count(2)->create(['user_id' => $user->id]);
        $book = Book::factory()->withPdf()->create();

        $this->actingAs($user)->post("/buku/{$book->id}/pinjam")->assertSessionHas('error');
        $this->assertSame(0, Loan::where('book_id', $book->id)->count());
    }

    public function test_renewal_extends_the_due_date_once(): void
    {
        $user = $this->member();
        $book = Book::factory()->withPdf()->create();
        $this->actingAs($user)->post("/buku/{$book->id}/pinjam");
        $before = Loan::first()->due_at;

        $this->patch("/buku/{$book->id}/pinjam")->assertSessionHas('success');
        $this->assertEqualsWithDelta(14, $before->diffInDays(Loan::first()->due_at, true), 0.1);

        $this->patch("/buku/{$book->id}/pinjam")->assertSessionHas('error', 'Pinjaman hanya bisa diperpanjang 1 kali.');
        $this->assertSame(1, Loan::first()->renewals);
    }

    public function test_returning_a_book_that_is_not_borrowed_reports_an_error(): void
    {
        $book = Book::factory()->withPdf()->create();

        $this->actingAs($this->member())->delete("/buku/{$book->id}/pinjam")->assertSessionHas('error');
    }

    public function test_the_database_prevents_two_open_loans_for_the_same_book(): void
    {
        $loan = Loan::factory()->create();

        $this->expectException(UniqueConstraintViolationException::class);
        Loan::factory()->create(['user_id' => $loan->user_id, 'book_id' => $loan->book_id]);
    }

    public function test_admins_cannot_use_member_features(): void
    {
        $book = Book::factory()->withPdf()->create();

        $this->actingAs($this->admin())->post("/buku/{$book->id}/pinjam")->assertForbidden();
        $this->put("/buku/{$book->id}/favorit")->assertForbidden();
        $this->put("/buku/{$book->id}/ulasan", ['rating' => 5])->assertForbidden();
        $this->get('/pinjaman')->assertForbidden();
    }

    public function test_guests_are_sent_to_login_when_borrowing(): void
    {
        $book = Book::factory()->withPdf()->create();

        $this->post("/buku/{$book->id}/pinjam")->assertRedirect(route('login'));
    }

    public function test_loan_status_and_days_left_are_derived_from_dates(): void
    {
        $this->assertSame('aktif', Loan::factory()->make()->status->value);
        $this->assertSame('berakhir', Loan::factory()->expired()->make()->status->value);
        $this->assertSame('selesai', Loan::factory()->returned()->make()->status->value);
        $this->assertSame(13, Loan::factory()->make(['due_at' => now()->addDays(13)->subMinute()])->days_left);
    }

    public function test_the_due_soon_window_comes_from_config(): void
    {
        $loan = Loan::factory()->make(['due_at' => now()->addDays(2)]);

        $this->assertTrue($loan->is_due_soon);

        config(['library.loan.due_soon_days' => 1]);

        $this->assertFalse($loan->is_due_soon);
    }

    public function test_an_overdue_loan_within_the_grace_period_has_no_late_days(): void
    {
        $loan = Loan::factory()->make(['due_at' => now()->subHours(2)]);

        $this->assertSame('berakhir', $loan->status->value);
        $this->assertSame(0, $loan->days_late);
        $this->assertSame(now()->subHours(2)->addDay()->toDateTimeString(), $loan->grace_ends_at->toDateTimeString());
    }

    public function test_due_soon_scope_selects_loans_nearing_the_due_date(): void
    {
        $soon = Loan::factory()->create(['due_at' => now()->addDays(2)]);
        Loan::factory()->create(['due_at' => now()->addDays(10)]);

        $this->assertSame([$soon->id], Loan::active()->dueSoon()->pluck('id')->all());
    }

    public function test_my_loans_page_lists_active_loans_and_history(): void
    {
        $user = $this->member();
        $active = Loan::factory()->create(['user_id' => $user->id]);
        $old = Loan::factory()->returned()->create(['user_id' => $user->id]);
        Loan::factory()->create(); // milik orang lain

        $this->actingAs($user)->get('/pinjaman')->assertOk()
            ->assertSee($active->book->title)->assertSee($old->book->title)->assertSee('1 / 5');
    }
}
