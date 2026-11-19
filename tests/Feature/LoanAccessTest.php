<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Loan;
use App\Services\LoanAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoanAccessTest extends TestCase
{
    use RefreshDatabase;

    private function access(): LoanAccess
    {
        return app(LoanAccess::class);
    }

    public function test_an_active_loan_is_found(): void
    {
        $user = $this->member();
        $book = Book::factory()->withPdf()->create();
        $loan = Loan::factory()->create(['user_id' => $user->id, 'book_id' => $book->id]);

        $this->assertTrue($this->access()->activeFor($user, $book)->is($loan));
    }

    public function test_a_returned_loan_no_longer_counts_as_active(): void
    {
        $user = $this->member();
        $book = Book::factory()->withPdf()->create();
        Loan::factory()->returned()->create(['user_id' => $user->id, 'book_id' => $book->id]);

        $this->assertNull($this->access()->activeFor($user, $book));
    }

    public function test_an_expired_loan_no_longer_counts_as_active(): void
    {
        $user = $this->member();
        $book = Book::factory()->withPdf()->create();
        Loan::factory()->expired()->create(['user_id' => $user->id, 'book_id' => $book->id]);

        $this->assertNull($this->access()->activeFor($user, $book));
    }

    public function test_another_members_loan_is_not_mine(): void
    {
        $user = $this->member();
        $book = Book::factory()->withPdf()->create();
        Loan::factory()->create(['book_id' => $book->id]);

        $this->assertNull($this->access()->activeFor($user, $book));
        $this->assertFalse($this->access()->hasBorrowed($user, $book));
    }

    public function test_a_returned_loan_still_counts_as_having_borrowed(): void
    {
        $user = $this->member();
        $book = Book::factory()->withPdf()->create();
        Loan::factory()->returned()->create(['user_id' => $user->id, 'book_id' => $book->id]);

        $this->assertTrue($this->access()->hasBorrowed($user, $book));
    }

    public function test_never_borrowed_is_false(): void
    {
        $user = $this->member();
        $book = Book::factory()->withPdf()->create();

        $this->assertFalse($this->access()->hasBorrowed($user, $book));
    }
}
