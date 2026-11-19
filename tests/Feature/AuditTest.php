<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Loan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditTest extends TestCase
{
    use RefreshDatabase;

    private function overdueLoan(int $userId): Loan
    {
        return Loan::factory()->create([
            'user_id' => $userId,
            'book_id' => Book::factory()->withPdf()->create()->id,
            'borrowed_at' => now()->subDays(20),
            'due_at' => now()->subHours(77),
            'fine_rate' => 1000,
        ]);
    }

    public function test_borrowing_is_logged_with_the_actor_and_the_loan(): void
    {
        $user = $this->member();
        $book = Book::factory()->withPdf()->create();

        $this->actingAs($user)->post("/buku/{$book->id}/pinjam")->assertSessionHas('success');

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'borrow_loan',
            'user_id' => $user->id,
            'entity_type' => 'loan',
            'entity_id' => Loan::firstOrFail()->id,
        ]);
    }

    public function test_renewing_is_logged(): void
    {
        $user = $this->member();
        $loan = Loan::factory()->create(['user_id' => $user->id, 'book_id' => Book::factory()->withPdf()->create()->id]);

        $this->actingAs($user)->patch("/buku/{$loan->book_id}/pinjam")->assertSessionHas('success');

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'renew_loan',
            'user_id' => $user->id,
            'entity_id' => $loan->id,
        ]);
    }

    public function test_returning_is_logged(): void
    {
        $user = $this->member();
        $loan = Loan::factory()->create(['user_id' => $user->id, 'book_id' => Book::factory()->withPdf()->create()->id]);

        $this->actingAs($user)->delete("/buku/{$loan->book_id}/pinjam")->assertSessionHas('success');

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'return_loan',
            'user_id' => $user->id,
            'entity_id' => $loan->id,
        ]);
        $this->assertDatabaseMissing('activity_logs', ['action' => 'fine_assessed']);
    }

    public function test_a_late_return_logs_the_return_and_the_fine(): void
    {
        $user = $this->member();
        $loan = $this->overdueLoan($user->id);

        $this->actingAs($user)->delete("/buku/{$loan->book_id}/pinjam")->assertSessionHas('success');

        $this->assertDatabaseHas('activity_logs', ['action' => 'return_loan', 'entity_id' => $loan->id, 'user_id' => $user->id]);
        $this->assertDatabaseHas('activity_logs', ['action' => 'fine_assessed', 'entity_id' => $loan->id, 'user_id' => $user->id]);
    }

    public function test_an_admin_ending_a_loan_logs_the_end_and_the_fine(): void
    {
        $admin = $this->admin();
        $loan = $this->overdueLoan($this->member()->id);

        $this->actingAs($admin)->patch("/admin/peminjaman/{$loan->id}")->assertSessionHas('success');

        $this->assertDatabaseHas('activity_logs', ['action' => 'finish_loan', 'entity_id' => $loan->id, 'user_id' => $admin->id]);
        $this->assertDatabaseHas('activity_logs', ['action' => 'fine_assessed', 'entity_id' => $loan->id, 'user_id' => $admin->id]);
    }
}
