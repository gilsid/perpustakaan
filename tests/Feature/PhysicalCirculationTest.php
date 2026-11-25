<?php

namespace Tests\Feature;

use App\Enums\BookCopyStatus;
use App\Enums\LoanFormat;
use App\Models\Book;
use App\Models\BookCopy;
use App\Models\User;
use App\Services\LoanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PhysicalCirculationTest extends TestCase
{
    use RefreshDatabase;

    private function copyFor(Book $book, string $code = 'FIS-0001-01'): BookCopy
    {
        return BookCopy::create([
            'book_id' => $book->id,
            'code' => $code,
            'status' => BookCopyStatus::Available,
        ]);
    }

    public function test_physical_loans_use_their_own_loan_period(): void
    {
        config(['library.loan.physical_days' => 7]);
        $copy = $this->copyFor(Book::factory()->create());

        $loan = app(LoanService::class)->checkOutCopy($this->member(), $copy, $this->admin());

        $this->assertSame(now()->addDays(7)->toDateString(), $loan->due_at->toDateString());
    }

    public function test_a_new_member_has_a_number_and_can_borrow_a_digital_book(): void
    {
        $this->post('/daftar', [
            'name' => 'Anggota Baru',
            'email' => 'baru@example.com',
            'password' => 'rahasia123',
            'password_confirmation' => 'rahasia123',
        ])->assertRedirect(route('home'));

        $user = User::firstWhere('email', 'baru@example.com');
        $this->assertSame(User::memberNumberFor($user->id), $user->member_number);

        $book = Book::factory()->withPdf()->create();
        $this->actingAs($user)->post("/buku/{$book->id}/pinjam")->assertSessionHas('success');
    }

    public function test_a_copy_can_be_checked_out_by_a_staff_member(): void
    {
        $admin = $this->admin();
        $user = $this->member();
        $book = Book::factory()->create();
        $copy = $this->copyFor($book);

        $loan = app(LoanService::class)->checkOutCopy($user, $copy, $admin);

        $this->assertSame(LoanFormat::Physical, $loan->format);
        $this->assertSame($copy->id, $loan->copy_id);
        $this->assertSame(BookCopyStatus::Loan, $copy->fresh()->status);
        $this->assertDatabaseHas('activity_logs', ['action' => 'checkout_copy', 'entity_id' => $loan->id]);
    }

    public function test_ending_a_physical_loan_checks_the_copy_in(): void
    {
        $admin = $this->admin();
        $book = Book::factory()->create();
        $copy = $this->copyFor($book);
        $loan = app(LoanService::class)->checkOutCopy($this->member(), $copy, $admin);

        $this->actingAs($admin)->post('/admin/sirkulasi/kembali', [
            'barcode' => $copy->code,
            'condition' => 'baik',
        ])->assertSessionHas('success');

        $this->assertSame(BookCopyStatus::Available, $copy->fresh()->status);
        $this->assertNotNull($loan->fresh()->returned_at);
        $this->assertDatabaseHas('activity_logs', ['action' => 'checkin_copy', 'entity_id' => $loan->id]);
    }

    public function test_a_physical_loan_cannot_be_ended_without_a_condition(): void
    {
        $admin = $this->admin();
        $book = Book::factory()->create();
        $copy = $this->copyFor($book);
        $loan = app(LoanService::class)->checkOutCopy($this->member(), $copy, $admin);

        $this->actingAs($admin)->patch("/admin/peminjaman/{$loan->id}")
            ->assertSessionHasErrors('condition');

        $this->assertNull($loan->fresh()->returned_at);
        $this->assertSame(BookCopyStatus::Loan, $copy->fresh()->status);
    }

    public function test_an_inactive_member_cannot_take_a_copy(): void
    {
        $admin = $this->admin();
        $member = User::factory()->inactive()->create();
        $book = Book::factory()->create();
        $copy = $this->copyFor($book);

        $this->actingAs($admin)->post('/admin/sirkulasi', [
            'member' => $member->member_number,
            'barcode' => $copy->code,
        ])->assertSessionHas('error', 'Hanya anggota aktif yang boleh meminjam.');

        $this->assertSame(BookCopyStatus::Available, $copy->fresh()->status);
    }

    public function test_a_physical_loan_does_not_grant_pdf_access(): void
    {
        $user = $this->member();
        $book = Book::factory()->withPdf()->create();
        $copy = $this->copyFor($book);
        app(LoanService::class)->checkOutCopy($user, $copy, $this->admin());

        $this->actingAs($user)->get(route('reader.show', $book))
            ->assertRedirect(route('books.show', $book));
    }

    public function test_the_shelf_hides_self_return_for_a_physical_loan(): void
    {
        $user = $this->member();
        $book = Book::factory()->withPdf()->create();
        $copy = $this->copyFor($book);
        app(LoanService::class)->checkOutCopy($user, $copy, $this->admin());

        $this->actingAs($user)->get('/pinjaman')->assertOk()
            ->assertSee('Kembalikan di meja sirkulasi')
            ->assertDontSee(route('reader.show', $book));
    }
}
