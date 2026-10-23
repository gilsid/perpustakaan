<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Loan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReaderTest extends TestCase
{
    use RefreshDatabase;

    public function test_reader_is_closed_without_an_active_loan(): void
    {
        $book = Book::factory()->withPdf()->create();
        $user = $this->member();

        $this->actingAs($user)->get("/baca/{$book->id}")->assertRedirect(route('books.show', $book))->assertSessionHas('warning');
        $this->get("/berkas/{$book->id}")->assertForbidden();
    }

    public function test_active_loan_opens_the_reader_and_streams_the_pdf(): void
    {
        $user = $this->member();
        $loan = Loan::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)->get("/baca/{$loan->book_id}")->assertOk()->assertSee('Pembaca PDF');

        $response = $this->get("/berkas/{$loan->book_id}");
        $response->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringContainsString('inline', $response->headers->get('Content-Disposition'));
        $this->assertStringStartsWith('%PDF-', $response->streamedContent() ?: file_get_contents($response->baseResponse->getFile()->getPathname()));
    }

    public function test_pdf_supports_range_requests(): void
    {
        $user = $this->member();
        $loan = Loan::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->get("/berkas/{$loan->book_id}", ['Range' => 'bytes=0-9']);

        $response->assertStatus(206)->assertHeader('Accept-Ranges', 'bytes');
        $this->assertStringStartsWith('bytes 0-9/', $response->headers->get('Content-Range'));
    }

    public function test_access_closes_after_returning_the_book_or_after_expiry(): void
    {
        $user = $this->member();
        $returned = Loan::factory()->returned()->create(['user_id' => $user->id]);
        $expired = Loan::factory()->expired()->create(['user_id' => $user->id]);

        $this->actingAs($user)->get("/berkas/{$returned->book_id}")->assertForbidden();
        $this->get("/berkas/{$expired->book_id}")->assertForbidden();
    }

    public function test_someone_elses_loan_grants_no_access(): void
    {
        $loan = Loan::factory()->create();

        $this->actingAs($this->member())->get("/berkas/{$loan->book_id}")->assertForbidden();
    }

    public function test_admin_can_read_any_pdf(): void
    {
        $book = Book::factory()->withPdf()->create();

        $this->actingAs($this->admin())->get("/baca/{$book->id}")->assertOk()->assertSee('Mode admin');
        $this->get("/berkas/{$book->id}")->assertOk();
    }

    public function test_books_without_pdf_return_404(): void
    {
        $book = Book::factory()->create();

        $this->actingAs($this->admin())->get("/baca/{$book->id}")->assertNotFound();
        $this->get("/berkas/{$book->id}")->assertNotFound();
    }

    public function test_missing_file_on_disk_returns_404(): void
    {
        $book = Book::factory()->create(['pdf_path' => 'books/hilang.pdf']);

        $this->actingAs($this->admin())->get("/berkas/{$book->id}")->assertNotFound();
    }

    public function test_pdf_response_has_its_own_frame_policy(): void
    {
        $book = Book::factory()->withPdf()->create();

        $this->actingAs($this->admin())->get("/berkas/{$book->id}")->assertHeader('Content-Security-Policy', "frame-ancestors 'self'");
    }
}
