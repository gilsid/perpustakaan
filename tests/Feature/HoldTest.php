<?php

namespace Tests\Feature;

use App\Enums\BookCopyStatus;
use App\Enums\HoldStatus;
use App\Exceptions\LoanException;
use App\Models\Book;
use App\Models\BookCopy;
use App\Models\Hold;
use App\Services\HoldService;
use App\Services\LoanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HoldTest extends TestCase
{
    use RefreshDatabase;

    private function bookWithCopies(int $total = 1, BookCopyStatus $status = BookCopyStatus::Loan): Book
    {
        $book = Book::factory()->create();
        foreach (range(1, $total) as $number) {
            BookCopy::create([
                'book_id' => $book->id,
                'code' => 'HOLD-'.$book->id.'-'.$number,
                'status' => $status,
            ]);
        }

        return $book;
    }

    public function test_a_hold_can_be_placed_when_every_copy_is_unavailable(): void
    {
        $user = $this->member();
        $book = $this->bookWithCopies(2);

        $this->actingAs($user)->post("/buku/{$book->id}/hold")->assertSessionHas('success');

        $this->assertDatabaseHas('holds', [
            'user_id' => $user->id,
            'book_id' => $book->id,
            'status' => HoldStatus::Active->value,
        ]);
        $this->assertDatabaseHas('activity_logs', ['action' => 'hold_placed']);
    }

    public function test_a_hold_cannot_be_placed_while_a_copy_is_available(): void
    {
        $book = $this->bookWithCopies(1, BookCopyStatus::Available);

        $this->actingAs($this->member())->post("/buku/{$book->id}/hold")
            ->assertSessionHas('error', 'Masih ada eksemplar tersedia. Ambil langsung di perpustakaan.');

        $this->assertDatabaseCount('holds', 0);
    }

    public function test_a_member_with_an_open_loan_cannot_hold_the_same_title(): void
    {
        $admin = $this->admin();
        $member = $this->member();
        $book = $this->bookWithCopies(1, BookCopyStatus::Available);
        $copy = BookCopy::where('book_id', $book->id)->firstOrFail();
        app(LoanService::class)->checkOutCopy($member, $copy, $admin);

        $this->actingAs($member)->post("/buku/{$book->id}/hold")
            ->assertSessionHas('error', 'Anda sudah meminjam buku ini.');

        $this->assertDatabaseCount('holds', 0);
    }

    public function test_holds_are_served_in_the_order_they_were_placed(): void
    {
        $book = $this->bookWithCopies();
        $first = $this->member();
        $second = $this->member();
        $holds = app(HoldService::class);

        $holds->place($first, $book);
        $holds->place($second, $book);

        $this->assertTrue($holds->firstForBook($book)->is(Hold::firstOrFail()));
    }

    public function test_checking_in_a_copy_reserves_it_for_the_first_hold(): void
    {
        $borrower = $this->member();
        $waiter = $this->member();
        $admin = $this->admin();
        $book = $this->bookWithCopies(1, BookCopyStatus::Available);
        $copy = BookCopy::firstOrFail();

        $loan = app(LoanService::class)->checkOutCopy($borrower, $copy, $admin);
        app(HoldService::class)->place($waiter, $book);

        $this->actingAs($admin)->post('/admin/sirkulasi/kembali', [
            'barcode' => $copy->code,
            'condition' => 'baik',
        ])->assertSessionHas('success');

        $this->assertSame(BookCopyStatus::OnHold, $copy->fresh()->status);
        $this->assertDatabaseHas('holds', [
            'user_id' => $waiter->id,
            'book_id' => $book->id,
            'copy_id' => $copy->id,
            'status' => HoldStatus::Ready->value,
        ]);
    }

    public function test_the_scheduled_command_expires_stale_holds(): void
    {
        $admin = $this->admin();
        $borrower = $this->member();
        $waiter = $this->member();
        $book = $this->bookWithCopies(1, BookCopyStatus::Available);
        $copy = BookCopy::firstOrFail();
        app(LoanService::class)->checkOutCopy($borrower, $copy, $admin);
        $hold = app(HoldService::class)->place($waiter, $book);
        $this->actingAs($admin)->post('/admin/sirkulasi/kembali', [
            'barcode' => $copy->code,
            'condition' => 'baik',
        ])->assertSessionHas('success');
        $hold->update(['pickup_expires_at' => now()->subMinute()]);

        $this->artisan('library:expire-holds')->assertSuccessful();

        $this->assertSame(HoldStatus::Expired, $hold->fresh()->status);
        $this->assertSame(BookCopyStatus::Available, $copy->fresh()->status);
    }

    public function test_an_expired_hold_is_not_fulfilled_at_check_out(): void
    {
        $admin = $this->admin();
        $borrower = $this->member();
        $waiter = $this->member();
        $book = $this->bookWithCopies(1, BookCopyStatus::Available);
        $copy = BookCopy::firstOrFail();
        app(LoanService::class)->checkOutCopy($borrower, $copy, $admin);
        $hold = app(HoldService::class)->place($waiter, $book);
        $this->actingAs($admin)->post('/admin/sirkulasi/kembali', [
            'barcode' => $copy->code,
            'condition' => 'baik',
        ])->assertSessionHas('success');
        $hold->update(['pickup_expires_at' => now()->subDay()]);

        app(LoanService::class)->checkOutCopy($waiter, BookCopy::findOrFail($copy->id), $admin);

        $this->assertSame(HoldStatus::Expired, $hold->fresh()->status);
        $this->assertNull($hold->fresh()->loan_id);
        $this->assertSame(BookCopyStatus::Loan, $copy->fresh()->status);
    }

    public function test_only_the_holder_can_check_out_a_reserved_copy(): void
    {
        $borrower = $this->member();
        $waiter = $this->member();
        $admin = $this->admin();
        $book = $this->bookWithCopies(1, BookCopyStatus::Available);
        $copy = BookCopy::firstOrFail();

        $loan = app(LoanService::class)->checkOutCopy($borrower, $copy, $admin);
        $hold = app(HoldService::class)->place($waiter, $book);
        $this->actingAs($admin)->post('/admin/sirkulasi/kembali', [
            'barcode' => $copy->code,
            'condition' => 'baik',
        ])->assertSessionHas('success');

        try {
            app(LoanService::class)->checkOutCopy($borrower, BookCopy::findOrFail($copy->id), $admin);
            $this->fail('Another member checked out a held copy.');
        } catch (LoanException $e) {
            $this->assertSame('Eksemplar ini sedang ditahan untuk hold anggota lain.', $e->getMessage());
        }

        app(LoanService::class)->checkOutCopy($waiter, BookCopy::findOrFail($copy->id), $admin);

        $this->assertSame(HoldStatus::Fulfilled, $hold->fresh()->status);
        $this->assertSame(BookCopyStatus::Loan, $copy->fresh()->status);
    }

    public function test_a_suspended_member_is_skipped_when_a_copy_is_reserved(): void
    {
        $admin = $this->admin();
        $borrower = $this->member();
        $suspended = $this->member();
        $ready = $this->member();
        $book = $this->bookWithCopies(1, BookCopyStatus::Available);
        $copy = BookCopy::firstOrFail();
        app(LoanService::class)->checkOutCopy($borrower, $copy, $admin);
        $holds = app(HoldService::class);
        $first = $holds->place($suspended, $book);
        $second = $holds->place($ready, $book);
        $suspended->forceFill(['suspend_until' => now()->addDays(3)])->save();

        $this->actingAs($admin)->post('/admin/sirkulasi/kembali', [
            'barcode' => $copy->code,
            'condition' => 'baik',
        ])->assertSessionHas('success');

        $this->assertSame(HoldStatus::Active, $first->fresh()->status);
        $this->assertSame(HoldStatus::Ready, $second->fresh()->status);
        $this->assertSame(BookCopyStatus::OnHold, $copy->fresh()->status);
    }

    public function test_a_hold_blocks_staff_renewal(): void
    {
        $borrower = $this->member();
        $admin = $this->admin();
        $book = $this->bookWithCopies(1, BookCopyStatus::Available);
        $copy = BookCopy::firstOrFail();

        $loan = app(LoanService::class)->checkOutCopy($borrower, $copy, $admin);
        app(HoldService::class)->place($this->member(), $book);

        $this->actingAs($admin)->patch("/admin/peminjaman/{$loan->id}/perpanjang")
            ->assertSessionHas('error', 'Ada hold pada judul ini. Kembalikan eksemplar dulu.');

        $this->assertSame(0, $loan->fresh()->renewals);
    }

    public function test_an_uncollected_hold_expires_and_frees_the_copy(): void
    {
        $book = $this->bookWithCopies();
        $copy = BookCopy::firstOrFail();
        $user = $this->member();
        $hold = app(HoldService::class)->place($user, $book);
        $hold->update([
            'status' => HoldStatus::Ready,
            'copy_id' => $copy->id,
            'ready_at' => now()->subDays(8),
            'pickup_expires_at' => now()->subDay(),
        ]);
        $copy->update(['status' => BookCopyStatus::OnHold]);

        app(HoldService::class)->expireStale();

        $this->assertSame(HoldStatus::Expired, $hold->fresh()->status);
        $this->assertSame(BookCopyStatus::Available, $copy->fresh()->status);
        $this->assertDatabaseHas('activity_logs', ['action' => 'hold_expired', 'user_id' => null]);
    }

    public function test_an_active_hold_is_consumed_when_the_member_takes_a_new_copy(): void
    {
        $admin = $this->admin();
        $borrower = $this->member();
        $waiter = $this->member();
        $book = $this->bookWithCopies(1, BookCopyStatus::Available);
        $oldCopy = BookCopy::firstOrFail();
        app(LoanService::class)->checkOutCopy($borrower, $oldCopy, $admin);
        $hold = app(HoldService::class)->place($waiter, $book);
        $newCopy = BookCopy::create([
            'book_id' => $book->id,
            'code' => 'HOLD-NEW-01',
            'status' => BookCopyStatus::Available,
        ]);

        app(LoanService::class)->checkOutCopy($waiter, $newCopy, $admin);

        $this->assertSame(HoldStatus::Fulfilled, $hold->fresh()->status);
        $this->assertSame($newCopy->id, $hold->fresh()->copy_id);

        $this->actingAs($admin)->post('/admin/sirkulasi/kembali', [
            'barcode' => $oldCopy->code,
            'condition' => 'baik',
        ])->assertSessionHas('success');

        $this->assertSame(BookCopyStatus::Available, $oldCopy->fresh()->status);
    }
}
