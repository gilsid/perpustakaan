<?php

namespace Tests\Feature;

use App\Enums\BookCopyStatus;
use App\Enums\HoldStatus;
use App\Models\Book;
use App\Models\BookCopy;
use App\Services\HoldService;
use App\Services\LoanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckInTest extends TestCase
{
    use RefreshDatabase;

    private function checkoutCopy(): array
    {
        $admin = $this->admin();
        $book = Book::factory()->create();
        $copy = BookCopy::create([
            'book_id' => $book->id,
            'code' => 'IN-'.$book->id.'-01',
            'status' => BookCopyStatus::Available,
        ]);
        $loan = app(LoanService::class)->checkOutCopy($this->member(), $copy, $admin);

        return [$admin, $book, $copy, $loan];
    }

    public function test_a_copy_can_be_checked_in_by_barcode(): void
    {
        [$admin, $book, $copy, $loan] = $this->checkoutCopy();

        $this->actingAs($admin)->post('/admin/sirkulasi/kembali', [
            'barcode' => $copy->code,
            'condition' => 'baik',
        ])->assertSessionHas('success');

        $this->assertSame(BookCopyStatus::Available, $copy->fresh()->status);
        $this->assertNotNull($loan->fresh()->returned_at);
        $this->assertDatabaseHas('activity_logs', ['action' => 'checkin_copy', 'entity_id' => $loan->id]);
    }

    public function test_a_damaged_copy_is_not_put_back_on_the_shelf_or_on_hold(): void
    {
        [$admin, $book, $copy, $loan] = $this->checkoutCopy();
        $hold = app(HoldService::class)->place($this->member(), $book);

        $this->actingAs($admin)->post('/admin/sirkulasi/kembali', [
            'barcode' => $copy->code,
            'condition' => 'rusak',
        ])->assertSessionHas('success');

        $this->assertSame(BookCopyStatus::Damaged, $copy->fresh()->status);
        $this->assertSame(HoldStatus::Active, $hold->fresh()->status);
    }

    public function test_an_unknown_barcode_is_rejected(): void
    {
        $this->actingAs($this->admin())->post('/admin/sirkulasi/kembali', [
            'barcode' => 'TIADA',
            'condition' => 'baik',
        ])->assertSessionHas('error', 'Eksemplar dengan barcode itu tidak ditemukan.');
    }
}
