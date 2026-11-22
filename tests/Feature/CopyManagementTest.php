<?php

namespace Tests\Feature;

use App\Enums\BookCopyStatus;
use App\Models\Book;
use App\Models\BookCopy;
use App\Services\FineService;
use App\Services\LoanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CopyManagementTest extends TestCase
{
    use RefreshDatabase;

    private function book(): Book
    {
        return Book::factory()->create();
    }

    public function test_an_admin_can_add_a_copy_with_a_replacement_cost(): void
    {
        $book = $this->book();

        $this->actingAs($this->admin())->post('/admin/eksemplar', [
            'book_id' => $book->id,
            'code' => 'BR-0001-01',
            'location' => 'Rak B',
            'status' => 'available',
            'replacement_cost' => 75000,
        ])->assertRedirect(route('admin.copies.index'));

        $this->assertDatabaseHas('book_copies', ['code' => 'BR-0001-01', 'replacement_cost' => 75000]);
        $this->assertDatabaseHas('activity_logs', ['action' => 'copy_created']);
    }

    public function test_two_copies_cannot_share_a_barcode(): void
    {
        $book = $this->book();
        BookCopy::create(['book_id' => $book->id, 'code' => 'BR-DUPLICAT', 'status' => BookCopyStatus::Available]);

        $this->actingAs($this->admin())->post('/admin/eksemplar', [
            'book_id' => $book->id,
            'code' => 'BR-DUPLICAT',
            'status' => 'available',
        ])->assertSessionHasErrors('code');
    }

    public function test_an_admin_can_change_a_copy(): void
    {
        $copy = BookCopy::create([
            'book_id' => $this->book()->id,
            'code' => 'BR-Ubah',
            'status' => BookCopyStatus::Available,
        ]);

        $this->actingAs($this->admin())->put("/admin/eksemplar/{$copy->id}", [
            'book_id' => $copy->book_id,
            'code' => 'BR-Ubah-2',
            'location' => 'Rak C',
            'status' => 'damaged',
            'replacement_cost' => 20000,
        ])->assertRedirect(route('admin.copies.index'));

        $this->assertSame('BR-Ubah-2', $copy->fresh()->code);
        $this->assertSame(BookCopyStatus::Damaged, $copy->fresh()->status);
    }

    public function test_a_copy_on_loan_cannot_be_deleted(): void
    {
        $admin = $this->admin();
        $copy = BookCopy::create([
            'book_id' => $this->book()->id,
            'code' => 'BR-Aktif',
            'status' => BookCopyStatus::Available,
        ]);
        app(LoanService::class)->checkOutCopy($this->member(), $copy, $admin);

        $this->actingAs($admin)->delete("/admin/eksemplar/{$copy->id}")
            ->assertSessionHas('error', 'Eksemplar sedang dipinjam dan tidak bisa dihapus.');

        $this->assertDatabaseHas('book_copies', ['id' => $copy->id]);
    }

    public function test_a_damaged_return_charges_the_replacement_cost_and_blocks_borrowing(): void
    {
        $admin = $this->admin();
        $member = $this->member();
        $copy = BookCopy::create([
            'book_id' => $this->book()->id,
            'code' => 'BR-Rusak',
            'status' => BookCopyStatus::Available,
            'replacement_cost' => 60000,
        ]);
        $loan = app(LoanService::class)->checkOutCopy($member, $copy, $admin);

        $this->actingAs($admin)->post('/admin/sirkulasi/kembali', [
            'barcode' => $copy->code,
            'condition' => 'rusak',
        ])->assertSessionHas('success');

        $this->assertSame(60000, $loan->fresh()->replacement_amount);
        $this->assertSame(60000, app(FineService::class)->unpaidTotal($member->fresh()));
        $this->assertDatabaseHas('activity_logs', ['action' => 'replacement_charged', 'entity_id' => $loan->id]);

        $book = Book::factory()->withPdf()->create();
        $this->actingAs($member)->post("/buku/{$book->id}/pinjam")->assertSessionHas('error');
    }

    public function test_paying_the_replacement_cost_restores_borrowing(): void
    {
        $admin = $this->admin();
        $member = $this->member();
        $copy = BookCopy::create([
            'book_id' => $this->book()->id,
            'code' => 'BR-Lunas',
            'status' => BookCopyStatus::Available,
            'replacement_cost' => 40000,
        ]);
        $loan = app(LoanService::class)->checkOutCopy($member, $copy, $admin);
        $this->actingAs($admin)->post('/admin/sirkulasi/kembali', ['barcode' => $copy->code, 'condition' => 'hilang']);

        $this->actingAs($admin)->patch("/admin/peminjaman/{$loan->id}/penggantian/lunas")->assertSessionHas('success');

        $this->assertSame(0, app(FineService::class)->unpaidTotal($member->fresh()));
        $this->assertDatabaseHas('activity_logs', ['action' => 'replacement_paid', 'entity_id' => $loan->id]);
    }

    public function test_a_replacement_cost_can_be_waived_with_a_reason(): void
    {
        $admin = $this->admin();
        $member = $this->member();
        $copy = BookCopy::create([
            'book_id' => $this->book()->id,
            'code' => 'BR-Bebas',
            'status' => BookCopyStatus::Available,
            'replacement_cost' => 30000,
        ]);
        $loan = app(LoanService::class)->checkOutCopy($member, $copy, $admin);
        $this->actingAs($admin)->post('/admin/sirkulasi/kembali', ['barcode' => $copy->code, 'condition' => 'rusak']);

        $this->actingAs($admin)->patch("/admin/peminjaman/{$loan->id}/penggantian/bebas", ['reason' => 'kesalahan pencatatan'])
            ->assertSessionHas('success');

        $this->assertNotNull($loan->fresh()->replacement_waived_at);
        $this->assertDatabaseHas('activity_logs', ['action' => 'replacement_waived', 'entity_id' => $loan->id]);
    }
}
