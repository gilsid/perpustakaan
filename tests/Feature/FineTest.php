<?php

namespace Tests\Feature;

use App\Enums\FineStatus;
use App\Models\ActivityLog;
use App\Models\Book;
use App\Models\Loan;
use App\Models\User;
use App\Services\FineService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FineTest extends TestCase
{
    use RefreshDatabase;

    /** Pinjaman yang sudah lewat tenggat 77 jam: 3 hari setelah grace 24 jam. */
    private function overdueLoan(User $user, ?Book $book = null, array $attributes = []): Loan
    {
        return Loan::factory()->create([
            'user_id' => $user->id,
            'book_id' => ($book ?? Book::factory()->withPdf()->create())->id,
            'borrowed_at' => now()->subDays(20),
            'due_at' => now()->subHours(77),
            'fine_rate' => 1000,
            ...$attributes,
        ]);
    }

    public function test_returning_on_time_creates_no_fine_and_no_suspension(): void
    {
        $user = $this->member();
        $book = Book::factory()->withPdf()->create();
        Loan::factory()->create(['user_id' => $user->id, 'book_id' => $book->id, 'fine_rate' => 1000]);

        $this->actingAs($user)->delete("/buku/{$book->id}/pinjam")
            ->assertSessionHas('success', 'Buku dikembalikan.');

        $loan = Loan::firstOrFail();
        $this->assertSame(0, $loan->late_days);
        $this->assertSame(0, $loan->fine_amount);
        $this->assertSame(FineStatus::None, $loan->fine_status);
        $this->assertNull($user->fresh()->suspend_until);
    }

    public function test_returning_late_records_the_fine_and_starts_the_suspension(): void
    {
        $user = $this->member();
        $loan = $this->overdueLoan($user);

        $this->actingAs($user)->delete("/buku/{$loan->book_id}/pinjam");

        $loan->refresh();
        $this->assertSame(3, $loan->late_days);
        $this->assertSame(3000, $loan->fine_amount);
        $this->assertSame(FineStatus::Unpaid, $loan->fine_status);

        $suspendUntil = $user->fresh()->suspend_until;
        $this->assertNotNull($suspendUntil);
        $this->assertEqualsWithDelta(6, now()->diffInDays($suspendUntil, true), 0.01);
    }

    public function test_the_flash_message_shows_the_fine_and_suspension(): void
    {
        $user = $this->member();
        $loan = $this->overdueLoan($user);

        $response = $this->actingAs($user)->delete("/buku/{$loan->book_id}/pinjam");

        $response->assertSessionHas('success', fn (string $message): bool => str_contains($message, 'Denda Rp3.000')
            && str_contains($message, '3 hari telat')
            && str_contains($message, 'diskors sampai'));
    }

    public function test_returning_twice_does_not_charge_twice(): void
    {
        $user = $this->member();
        $loan = $this->overdueLoan($user, attributes: ['fine_rate' => 500]);

        $this->actingAs($user)->delete("/buku/{$loan->book_id}/pinjam")->assertSessionHas('success');
        $this->delete("/buku/{$loan->book_id}/pinjam")->assertSessionHas('error');

        $loan->refresh();
        $this->assertSame(3, $loan->late_days);
        $this->assertSame(1500, $loan->fine_amount);
    }

    public function test_the_rate_is_taken_from_the_loan_snapshot_not_the_current_config(): void
    {
        config(['library.fine.rate_per_day' => 9999]);
        $user = $this->member();
        $loan = $this->overdueLoan($user, attributes: ['fine_rate' => 500]);

        $this->actingAs($user)->delete("/buku/{$loan->book_id}/pinjam");

        $this->assertSame(1500, $loan->fresh()->fine_amount);
    }

    public function test_the_fine_is_capped(): void
    {
        $user = $this->member();
        $loan = $this->overdueLoan($user, attributes: [
            'borrowed_at' => now()->subDays(120),
            'due_at' => now()->subDays(100)->subHour(),
        ]);

        $this->actingAs($user)->delete("/buku/{$loan->book_id}/pinjam");

        $loan->refresh();
        $this->assertSame(50000, $loan->fine_amount);
        $this->assertSame(100, $loan->late_days);
        $this->assertEqualsWithDelta(30, now()->diffInDays($user->fresh()->suspend_until, true), 0.01);
    }

    public function test_suspended_members_cannot_borrow(): void
    {
        $user = $this->member();
        $user->forceFill(['suspend_until' => now()->addDays(3)])->save();
        $book = Book::factory()->withPdf()->create();

        $this->actingAs($user)->post("/buku/{$book->id}/pinjam")
            ->assertSessionHas('error', fn (string $m): bool => str_contains($m, 'diskors sampai'));

        $this->assertSame(0, Loan::count());
    }

    public function test_members_with_unpaid_fines_cannot_borrow(): void
    {
        $user = $this->member();
        Loan::factory()->returned()->create([
            'user_id' => $user->id,
            'fine_amount' => 2000,
            'late_days' => 2,
            'fine_rate' => 1000,
        ]);
        $book = Book::factory()->withPdf()->create();

        $this->actingAs($user)->post("/buku/{$book->id}/pinjam")
            ->assertSessionHas('error', fn (string $m): bool => str_contains($m, 'denda belum lunas')
                && str_contains($m, 'Rp2.000'));

        $this->assertSame(1, Loan::count());
    }

    public function test_an_overdue_open_loan_blocks_new_borrows_and_is_not_auto_closed(): void
    {
        $user = $this->member();
        $overdue = $this->overdueLoan($user);
        $other = Book::factory()->withPdf()->create();

        $this->actingAs($user)->post("/buku/{$other->id}/pinjam")
            ->assertSessionHas('error', fn (string $m): bool => str_contains($m, 'lewat tenggat'));

        // Regresi: perilaku lama menutup pinjaman telat dengan returned_at = due_at,
        // sehingga keterlambatan hilang dari data. Sekarang harus tetap terbuka.
        $this->assertNull($overdue->fresh()->returned_at);
        $this->assertSame(1, Loan::count());
    }

    public function test_a_member_can_borrow_again_after_returning_and_paying(): void
    {
        $user = $this->member();
        $loan = $this->overdueLoan($user);

        $this->actingAs($user)->delete("/buku/{$loan->book_id}/pinjam");
        $this->assertSame(FineStatus::Unpaid, $loan->fresh()->fine_status);

        $this->actingAs($this->admin())->patch("/admin/peminjaman/{$loan->id}/denda/lunas")
            ->assertSessionHas('success');

        $this->assertNotNull($loan->fresh()->fine_paid_at);
        $this->assertSame(0, app(FineService::class)->unpaidTotal($user->fresh()));

        // Skorsing masih berjalan, jadi peminjaman tetap ditolak sampai tanggalnya lewat.
        $book = Book::factory()->withPdf()->create();
        $this->actingAs($user)->post("/buku/{$book->id}/pinjam")->assertSessionHas('error');

        $user->forceFill(['suspend_until' => now()->subMinute()])->save();
        $this->actingAs($user)->post("/buku/{$book->id}/pinjam")->assertSessionHas('success');
    }

    public function test_admin_ending_an_overdue_loan_records_the_fine(): void
    {
        $loan = $this->overdueLoan($this->member());

        $this->actingAs($this->admin())->patch("/admin/peminjaman/{$loan->id}")
            ->assertSessionHas('success', fn (string $m): bool => str_contains($m, 'Denda Rp3.000'));

        $loan->refresh();
        $this->assertNotNull($loan->returned_at);
        $this->assertSame(3, $loan->late_days);
        $this->assertSame(3000, $loan->fine_amount);
    }

    public function test_paying_twice_is_rejected(): void
    {
        $admin = $this->admin();
        $loan = $this->overdueLoan($this->member(), attributes: ['fine_amount' => 3000, 'late_days' => 3]);

        $this->actingAs($admin)->patch("/admin/peminjaman/{$loan->id}/denda/lunas")->assertSessionHas('success');
        $this->patch("/admin/peminjaman/{$loan->id}/denda/lunas")->assertSessionHas('error');
    }

    public function test_waiving_requires_a_reason_and_keeps_the_fine_unpaid(): void
    {
        $admin = $this->admin();
        $loan = $this->overdueLoan($this->member(), attributes: ['fine_amount' => 3000, 'late_days' => 3]);

        $this->actingAs($admin)->patch("/admin/peminjaman/{$loan->id}/denda/bebas", ['reason' => ''])
            ->assertSessionHasErrors('reason');

        $this->assertSame(FineStatus::Unpaid, $loan->fresh()->fine_status);
    }

    public function test_waiving_clears_the_fine_and_the_suspension(): void
    {
        $admin = $this->admin();
        $user = $this->member();
        $user->forceFill(['suspend_until' => now()->addDays(5)])->save();
        $loan = $this->overdueLoan($user, attributes: ['fine_amount' => 3000, 'late_days' => 3]);

        $this->actingAs($admin)->patch("/admin/peminjaman/{$loan->id}/denda/bebas", ['reason' => 'Anggota sakit, bukti surat dokter'])
            ->assertSessionHas('success');

        $loan->refresh();
        $this->assertSame(FineStatus::Waived, $loan->fine_status);
        $this->assertSame($admin->id, $loan->fine_waived_by);
        $this->assertSame(3000, $loan->fine_amount, 'Nominal asli tetap tercatat untuk audit.');
        $this->assertSame(0, app(FineService::class)->unpaidTotal($user->fresh()));
        $this->assertNull($user->fresh()->suspend_until);
        $this->assertDatabaseHas('activity_logs', ['action' => 'fine_waived', 'entity_id' => $loan->id]);
    }

    public function test_my_loans_page_lists_the_fine(): void
    {
        $user = $this->member();
        $loan = $this->overdueLoan($user);

        $this->actingAs($user)->delete("/buku/{$loan->book_id}/pinjam");

        $this->actingAs($user)->get('/pinjaman')->assertOk()
            ->assertSee('Rp3.000')
            ->assertSee('3 hari')
            ->assertSee('Belum lunas');
    }

    public function test_my_loans_page_surfaces_overdue_loans_with_a_return_button(): void
    {
        $user = $this->member();
        $this->overdueLoan($user);

        $this->actingAs($user)->get('/pinjaman')->assertOk()
            ->assertSee('Terlambat dikembalikan')
            ->assertSee('Kembalikan sekarang');
    }

    public function test_an_overdue_loan_inside_the_grace_period_is_not_called_late(): void
    {
        $user = $this->member();
        Loan::factory()->create([
            'user_id' => $user->id,
            'book_id' => Book::factory()->withPdf()->create()->id,
            'borrowed_at' => now()->subDays(15),
            'due_at' => now()->subHours(2),
        ]);

        $this->actingAs($user)->get('/pinjaman')->assertOk()
            ->assertSee('masih dalam masa tenggang')
            ->assertSee('Denda mulai')
            ->assertDontSee('hari telat');
    }

    public function test_my_loans_page_calls_a_loan_late_once_the_grace_period_ends(): void
    {
        $user = $this->member();
        $this->overdueLoan($user);

        $this->actingAs($user)->get('/pinjaman')->assertOk()
            ->assertSee('3 hari telat')
            ->assertDontSee('masih dalam masa tenggang');
    }

    public function test_book_page_explains_why_borrowing_is_blocked(): void
    {
        $user = $this->member();
        $user->forceFill(['suspend_until' => now()->addDays(3)])->save();
        $book = Book::factory()->withPdf()->create();

        $this->actingAs($user)->get("/buku/{$book->id}")->assertOk()
            ->assertSee('Akun Anda diskors sampai')
            ->assertSee('Peminjaman dibuka lagi');
    }

    public function test_admin_can_filter_unpaid_fines_only(): void
    {
        $admin = $this->admin();
        $unpaid = $this->overdueLoan($this->member(), attributes: ['fine_amount' => 3000, 'late_days' => 3]);
        $paid = $this->overdueLoan($this->member(), attributes: ['fine_amount' => 2000, 'late_days' => 2, 'fine_paid_at' => now()]);

        $this->actingAs($admin)->get('/admin/peminjaman?denda=1')->assertOk()
            ->assertSee($unpaid->book->title)
            ->assertDontSee($paid->book->title);
    }

    public function test_the_waive_dialog_names_a_generic_charge(): void
    {
        $this->actingAs($this->admin())->get('/admin/peminjaman')
            ->assertOk()
            ->assertSee('<button type="submit" class="btn btn--primary">Bebaskan tagihan</button>', false);
    }

    public function test_only_admins_can_settle_or_waive_fines(): void
    {
        $loan = $this->overdueLoan($this->member(), attributes: ['fine_amount' => 3000, 'late_days' => 3]);

        $this->actingAs($this->member())->patch("/admin/peminjaman/{$loan->id}/denda/lunas")->assertForbidden();
        $this->actingAs($this->member())->patch("/admin/peminjaman/{$loan->id}/denda/bebas", ['reason' => 'coba-coba'])->assertForbidden();
    }

    public function test_a_paid_fine_is_logged(): void
    {
        $admin = $this->admin();
        $loan = $this->overdueLoan($this->member(), attributes: ['fine_amount' => 3000, 'late_days' => 3]);

        $this->actingAs($admin)->patch("/admin/peminjaman/{$loan->id}/denda/lunas");

        $this->assertTrue(ActivityLog::query()->where('action', 'fine_paid')->where('entity_id', $loan->id)->exists());
    }

    public function test_borrowing_snapshots_the_current_rate(): void
    {
        config(['library.fine.rate_per_day' => 750]);
        $user = $this->member();
        $book = Book::factory()->withPdf()->create();

        $this->actingAs($user)->post("/buku/{$book->id}/pinjam")->assertSessionHas('success');

        $this->assertSame(750, Loan::firstOrFail()->fine_rate);
    }

    public function test_ending_an_on_time_loan_records_no_fine_or_suspension(): void
    {
        $user = $this->member();
        $loan = Loan::factory()->create(['user_id' => $user->id, 'fine_rate' => 1000]);

        $this->actingAs($this->admin())->patch("/admin/peminjaman/{$loan->id}")
            ->assertSessionHas('success', 'Pinjaman diakhiri.');

        $loan->refresh();
        $this->assertSame(0, $loan->late_days);
        $this->assertSame(0, $loan->fine_amount);
        $this->assertNull($user->fresh()->suspend_until);
        $this->assertFalse(ActivityLog::query()->where('action', 'fine_assessed')->exists());
    }

    public function test_a_settled_fine_cannot_be_waived(): void
    {
        $admin = $this->admin();
        $loan = $this->overdueLoan($this->member(), attributes: ['fine_amount' => 3000, 'late_days' => 3]);

        $this->actingAs($admin)->patch("/admin/peminjaman/{$loan->id}/denda/lunas")->assertSessionHas('success');

        $this->patch("/admin/peminjaman/{$loan->id}/denda/bebas", ['reason' => 'Kesalahan sistem'])
            ->assertSessionHas('error');

        $this->assertNull($loan->fresh()->fine_waived_at);
    }

    public function test_a_waived_fine_cannot_be_settled_or_waived_again(): void
    {
        $admin = $this->admin();
        $loan = $this->overdueLoan($this->member(), attributes: ['fine_amount' => 3000, 'late_days' => 3]);

        $this->actingAs($admin)->patch("/admin/peminjaman/{$loan->id}/denda/bebas", ['reason' => 'Kesalahan sistem'])
            ->assertSessionHas('success');

        $this->patch("/admin/peminjaman/{$loan->id}/denda/lunas")->assertSessionHas('error');
        $this->patch("/admin/peminjaman/{$loan->id}/denda/bebas", ['reason' => 'Coba lagi'])->assertSessionHas('error');

        $this->assertNull($loan->fresh()->fine_paid_at);
        $this->assertSame(FineStatus::Waived, $loan->fresh()->fine_status);
    }

    public function test_the_waive_reason_error_is_shown_on_the_admin_page(): void
    {
        $admin = $this->admin();
        $loan = $this->overdueLoan($this->member(), attributes: ['fine_amount' => 3000, 'late_days' => 3]);

        $this->actingAs($admin)->from('/admin/peminjaman?denda=1')->followingRedirects()
            ->patch("/admin/peminjaman/{$loan->id}/denda/bebas", ['reason' => 'no'])
            ->assertSee('Alasan pembebasan minimal 5 karakter.');
    }

    public function test_a_zero_rate_still_suspends_but_charges_nothing(): void
    {
        config(['library.fine.rate_per_day' => 0]);
        $user = $this->member();
        $book = Book::factory()->withPdf()->create();
        $this->actingAs($user)->post("/buku/{$book->id}/pinjam")->assertSessionHas('success');

        $loan = Loan::firstOrFail();
        $loan->update(['borrowed_at' => now()->subDays(20), 'due_at' => now()->subHours(77)]);

        $this->actingAs($user)->delete("/buku/{$book->id}/pinjam")
            ->assertSessionHas('success', fn (string $m): bool => str_contains($m, 'diskors sampai'));

        $loan->refresh();
        $this->assertSame(3, $loan->late_days);
        $this->assertSame(0, $loan->fine_amount);
        $this->assertNotNull($user->fresh()->suspend_until);
    }

    public function test_a_suspended_member_can_still_return_books(): void
    {
        $user = $this->member();
        $user->forceFill(['suspend_until' => now()->addDays(2)])->save();
        $loan = $this->overdueLoan($user);

        $this->actingAs($user)->delete("/buku/{$loan->book_id}/pinjam")->assertSessionHas('success');

        $this->assertNotNull($loan->fresh()->returned_at);
    }

    public function test_an_existing_longer_suspension_is_not_shortened(): void
    {
        $user = $this->member();
        $user->forceFill(['suspend_until' => now()->addDays(20)])->save();
        $loan = $this->overdueLoan($user);

        $this->actingAs($user)->delete("/buku/{$loan->book_id}/pinjam")->assertSessionHas('success');

        $this->assertEqualsWithDelta(20, now()->diffInDays($user->fresh()->suspend_until, true), 0.01);
    }
}
