<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Category;
use App\Models\Review;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_and_category_pages_render(): void
    {
        $category = Category::factory()->create(['name' => 'Sains']);
        Book::factory()->for($category)->create(['title' => 'Kosmos']);

        $this->get('/')->assertOk()->assertSee('Kosmos')->assertSee('Sains');
        $this->get('/kategori')->assertOk()->assertSee('Sains');
    }

    public function test_empty_catalog_renders(): void
    {
        $this->get('/')->assertOk()->assertSee('Katalog masih kosong');
        $this->get('/katalog')->assertOk()->assertSee('Tidak ada buku yang cocok');
    }

    public function test_search_is_case_insensitive_and_matches_author_and_isbn(): void
    {
        Book::factory()->create(['title' => 'Bumi Manusia', 'author' => 'Pramoedya', 'isbn' => '978-602-1234-56-7']);
        Book::factory()->create(['title' => 'Lainnya', 'author' => 'Orang Lain']);

        $this->get('/katalog?q=BUMI')->assertSee('Bumi Manusia')->assertDontSee('Lainnya');
        $this->get('/katalog?q=pramoedya')->assertSee('Bumi Manusia');
        $this->get('/katalog?q=602-1234')->assertSee('Bumi Manusia');
    }

    public function test_like_wildcards_in_search_are_literal(): void
    {
        Book::factory()->create(['title' => 'Buku Biasa']);

        $this->get('/katalog?q=%25')->assertSee('Tidak ada buku yang cocok');
        $this->get('/katalog?q=_')->assertSee('Tidak ada buku yang cocok');
    }

    public function test_filters_by_category_year_language_and_format(): void
    {
        $a = Category::factory()->create(['name' => 'Fiksi']);
        $b = Category::factory()->create(['name' => 'Sejarah']);
        Book::factory()->for($a)->create(['title' => 'Novel Lama', 'published_year' => 1990, 'language' => 'Indonesia']);
        Book::factory()->for($b)->withPdf()->create(['title' => 'Sejarah Baru', 'published_year' => 2020, 'language' => 'Inggris']);

        $this->get('/katalog?kategori='.$a->slug)->assertSee('Novel Lama')->assertDontSee('Sejarah Baru');
        $this->get('/katalog?dari=2000')->assertSee('Sejarah Baru')->assertDontSee('Novel Lama');
        $this->get('/katalog?sampai=2000')->assertSee('Novel Lama')->assertDontSee('Sejarah Baru');
        $this->get('/katalog?bahasa=Inggris')->assertSee('Sejarah Baru')->assertDontSee('Novel Lama');
        $this->get('/katalog?digital=1')->assertSee('Sejarah Baru')->assertDontSee('Novel Lama');
    }

    public function test_every_sort_option_works(): void
    {
        Book::factory()->count(3)->create();

        foreach (['relevan', 'terbaru', 'judul', 'rating', 'tahun', 'populer'] as $sort) {
            $this->get("/katalog?q=a&urut=$sort")->assertOk();
            $this->get("/katalog?urut=$sort")->assertOk();
        }
    }

    public function test_rating_sort_puts_best_rated_first(): void
    {
        $bad = Book::factory()->create(['title' => 'Buku Jelek']);
        $good = Book::factory()->create(['title' => 'Buku Bagus']);
        Review::factory()->for($bad)->create(['rating' => 1]);
        Review::factory()->for($good)->create(['rating' => 5]);

        $this->get('/katalog?urut=rating')->assertSeeInOrder(['Buku Bagus', 'Buku Jelek']);
    }

    public function test_odd_query_parameters_never_cause_errors(): void
    {
        Book::factory()->create();

        $this->get('/katalog?urut[]=x&dari=abc&halaman=-5&q[]=1&kategori[]=a&digital[]=1&bahasa[]=x')->assertOk();
        $this->get('/katalog?halaman=99999')->assertOk();
        $this->get('/katalog?dari=99999999999999999999')->assertOk();
    }

    public function test_pagination_uses_the_halaman_parameter(): void
    {
        Book::factory()->count(30)->create();

        $this->get('/katalog')->assertSee('halaman=2', false);
        $this->get('/katalog?halaman=3')->assertOk()->assertSee('Sebelumnya');
    }

    public function test_active_filters_are_shown_as_removable_chips(): void
    {
        Book::factory()->create();

        $this->get('/katalog?q=abc&digital=1')->assertSee('Kata kunci: abc')->assertSee('Bisa dibaca online')->assertSee('Hapus semua filter');
    }
}
