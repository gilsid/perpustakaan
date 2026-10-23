<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Loan;
use App\Models\Review;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewAndFavoriteTest extends TestCase
{
    use RefreshDatabase;

    public function test_reviews_require_having_borrowed_the_book(): void
    {
        $book = Book::factory()->create();

        $this->actingAs($this->member())->put("/buku/{$book->id}/ulasan", ['rating' => 5, 'comment' => 'Bagus'])
            ->assertSessionHas('error', 'Pinjam buku ini dulu untuk bisa menulis ulasan.');
        $this->assertSame(0, Review::count());
    }

    public function test_one_review_per_member_per_book_and_it_can_be_edited(): void
    {
        $user = $this->member();
        $loan = Loan::factory()->returned()->create(['user_id' => $user->id]);
        $url = "/buku/{$loan->book_id}/ulasan";

        $this->actingAs($user)->put($url, ['rating' => 4, 'comment' => 'Ulasan <i>pertama</i>'])->assertSessionHas('success');
        $this->put($url, ['rating' => 5, 'comment' => 'Ulasan diubah'])->assertSessionHas('success', 'Ulasan Anda diperbarui.');

        $this->assertSame(1, Review::count());
        $this->assertSame('Ulasan diubah', Review::first()->comment);
        $this->get("/buku/{$loan->book_id}")->assertSee('Ulasan diubah')->assertSee('Rating 5,0 dari 5');
    }

    public function test_review_html_is_escaped(): void
    {
        $user = $this->member();
        $loan = Loan::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)->put("/buku/{$loan->book_id}/ulasan", ['rating' => 3, 'comment' => '<script>alert(1)</script>']);

        $this->get("/buku/{$loan->book_id}")->assertDontSee('<script>alert(1)</script>', false)->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
    }

    public function test_rating_must_be_between_one_and_five(): void
    {
        $user = $this->member();
        $loan = Loan::factory()->create(['user_id' => $user->id]);

        foreach ([0, 6, 9, 'abc', ''] as $bad) {
            $this->actingAs($user)->put("/buku/{$loan->book_id}/ulasan", ['rating' => $bad])->assertSessionHasErrors('rating');
        }
        $this->assertSame(0, Review::count());
    }

    public function test_review_form_is_hidden_until_the_member_has_borrowed(): void
    {
        $book = Book::factory()->withPdf()->create();
        $user = $this->member();

        $this->actingAs($user)->get("/buku/{$book->id}")->assertSee('Pinjam buku ini untuk bisa menulis ulasan.');

        Loan::factory()->create(['user_id' => $user->id, 'book_id' => $book->id]);
        $this->get("/buku/{$book->id}")->assertSee('Tulis ulasan');
    }

    public function test_owners_and_admins_can_delete_reviews_but_others_cannot(): void
    {
        $review = Review::factory()->create();
        $owner = $review->user;

        $this->actingAs($this->member())->delete("/ulasan/{$review->id}")->assertForbidden();
        $this->assertModelExists($review);

        $this->actingAs($owner)->delete("/ulasan/{$review->id}")->assertSessionHas('success');
        $this->assertModelMissing($review);

        $other = Review::factory()->create();
        $this->actingAs($this->admin())->delete("/ulasan/{$other->id}")->assertSessionHas('success');
        $this->assertModelMissing($other);
        $this->assertDatabaseHas('activity_logs', ['action' => 'delete_review']);
    }

    public function test_favorites_can_be_added_and_removed_idempotently(): void
    {
        $user = $this->member();
        $book = Book::factory()->create(['title' => 'Buku Favorit Saya']);

        $this->actingAs($user)->put("/buku/{$book->id}/favorit")->assertSessionHas('success');
        $this->put("/buku/{$book->id}/favorit");
        $this->assertSame(1, $user->favorites()->count());

        $this->get('/favorit')->assertOk()->assertSee('Buku Favorit Saya');
        $this->get("/buku/{$book->id}")->assertSee('Tersimpan di favorit');

        $this->delete("/buku/{$book->id}/favorit")->assertSessionHas('success');
        $this->delete("/buku/{$book->id}/favorit");
        $this->assertSame(0, $user->favorites()->count());
        $this->get('/favorit')->assertSee('Belum ada buku yang disimpan');
    }

    public function test_deleting_a_book_removes_its_reviews_favorites_and_loans(): void
    {
        $loan = Loan::factory()->create();
        Review::factory()->create(['book_id' => $loan->book_id]);
        $loan->user->favorites()->attach($loan->book_id);

        $loan->book->delete();

        $this->assertDatabaseCount('loans', 0);
        $this->assertDatabaseCount('reviews', 0);
        $this->assertDatabaseCount('favorites', 0);
    }
}
