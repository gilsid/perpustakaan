<?php

namespace Tests\Feature\Admin;

use App\Models\Book;
use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BookManagementTest extends TestCase
{
    use RefreshDatabase;

    private function pdf(string $name = 'buku.pdf'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, "%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF\n");
    }

    private function payload(array $extra = []): array
    {
        // $extra didahulukan agar nilai yang ingin diganti menang atas nilai bawaan.
        return $extra + [
            'title' => 'Buku Uji', 'author' => 'Penulis Uji', 'category_id' => Category::factory()->create()->id,
            'published_year' => '2020', 'isbn' => '978-602-1234-56-7', 'description' => 'Deskripsi uji',
        ];
    }

    public function test_admin_creates_a_book_with_cover_and_pdf(): void
    {
        $this->actingAs($this->admin())
            ->post('/admin/buku', $this->payload(['cover' => UploadedFile::fake()->image('sampul.png', 400, 600), 'pdf' => $this->pdf()]))
            ->assertRedirect(route('admin.books.index'))->assertSessionHas('success');

        $book = Book::firstWhere('title', 'Buku Uji');
        $this->assertTrue($book->has_pdf);
        Storage::disk('local')->assertExists($book->pdf_path);
        Storage::disk('public')->assertExists($book->cover_path);
        $this->assertStringEndsWith('.jpg', $book->cover_path, 'sampul di-encode ulang ke JPEG');
        $this->assertDatabaseHas('activity_logs', ['action' => 'create_book', 'entity_id' => $book->id]);
    }

    public function test_cover_urls_are_relative_so_they_work_on_any_host_and_port(): void
    {
        // Storage::fake() memakai konfigurasinya sendiri, jadi baca berkas konfigurasi aslinya.
        $config = require config_path('filesystems.php');
        $this->assertSame('/storage', $config['disks']['public']['url']);

        $this->actingAs($this->admin())->post('/admin/buku', $this->payload(['cover' => UploadedFile::fake()->image('a.png')]));
        $this->assertStringStartsWith('/storage/covers/c_', Book::first()->cover_url);
    }

    public function test_pdf_is_stored_on_the_private_disk_not_the_public_one(): void
    {
        $this->actingAs($this->admin())->post('/admin/buku', $this->payload(['pdf' => $this->pdf()]));

        $book = Book::first();
        Storage::disk('public')->assertMissing($book->pdf_path);
        $this->assertSame([], Storage::disk('public')->allFiles('books'));
    }

    public function test_a_php_file_disguised_as_an_image_is_rejected(): void
    {
        $evil = UploadedFile::fake()->createWithContent('evil.jpg', "<?php echo 'pwned'; ?>");

        $this->actingAs($this->admin())->post('/admin/buku', $this->payload(['cover' => $evil]))->assertSessionHasErrors('cover');

        $this->assertSame(0, Book::count());
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_svg_and_non_pdf_files_are_rejected(): void
    {
        $svg = UploadedFile::fake()->createWithContent('x.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');
        $fakePdf = UploadedFile::fake()->createWithContent('fake.pdf', 'ini bukan pdf');
        $docAsPdf = UploadedFile::fake()->createWithContent('doc.pdf', "PK\x03\x04 bukan pdf");

        $admin = $this->admin();
        $this->actingAs($admin)->post('/admin/buku', $this->payload(['cover' => $svg]))->assertSessionHasErrors('cover');
        $this->post('/admin/buku', $this->payload(['pdf' => $fakePdf]))->assertSessionHasErrors('pdf');
        $this->post('/admin/buku', $this->payload(['pdf' => $docAsPdf]))->assertSessionHasErrors('pdf');
        $this->assertSame(0, Book::count());
    }

    public function test_file_size_limits_are_enforced(): void
    {
        $bigCover = UploadedFile::fake()->image('besar.jpg')->size(4000);
        $bigPdf = $this->pdf()->size(52000);

        $admin = $this->admin();
        $this->actingAs($admin)->post('/admin/buku', $this->payload(['cover' => $bigCover]))->assertSessionHasErrors('cover');
        $this->post('/admin/buku', $this->payload(['pdf' => $bigPdf]))->assertSessionHasErrors('pdf');
    }

    public function test_validation_messages_for_the_book_form(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->post('/admin/buku', ['title' => '', 'author' => '', 'category_id' => ''])
            ->assertSessionHasErrors(['title' => 'Judul wajib diisi.', 'author' => 'Penulis wajib diisi.', 'category_id' => 'Kategori wajib diisi.']);
        $this->post('/admin/buku', $this->payload(['published_year' => '99999']))->assertSessionHasErrors('published_year');
        $this->post('/admin/buku', $this->payload(['isbn' => 'abc<script>']))->assertSessionHasErrors(['isbn' => 'ISBN hanya boleh berisi angka, spasi, dan tanda hubung.']);
        $this->post('/admin/buku', $this->payload(['category_id' => 99999]))->assertSessionHasErrors('category_id');
    }

    public function test_titles_with_html_are_escaped_on_the_book_page(): void
    {
        $book = Book::factory()->create(['title' => '<script>alert(1)</script> Judul']);

        $this->get("/buku/{$book->id}")->assertDontSee('<script>alert(1)', false)->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
    }

    public function test_updating_replaces_files_and_removes_the_old_ones(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->post('/admin/buku', $this->payload(['cover' => UploadedFile::fake()->image('a.png'), 'pdf' => $this->pdf()]));
        $book = Book::first();
        [$oldCover, $oldPdf] = [$book->cover_path, $book->pdf_path];

        $this->put("/admin/buku/{$book->id}", $this->payload(['title' => 'Judul Baru', 'cover' => UploadedFile::fake()->image('b.png'), 'pdf' => $this->pdf('baru.pdf')]))
            ->assertRedirect(route('admin.books.index'));

        $book->refresh();
        $this->assertSame('Judul Baru', $book->title);
        $this->assertNotSame($oldCover, $book->cover_path);
        Storage::disk('public')->assertMissing($oldCover);
        Storage::disk('local')->assertMissing($oldPdf);
        Storage::disk('local')->assertExists($book->pdf_path);
    }

    public function test_files_can_be_removed_and_are_kept_when_nothing_is_uploaded(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->post('/admin/buku', $this->payload(['cover' => UploadedFile::fake()->image('a.png'), 'pdf' => $this->pdf()]));
        $book = Book::first();
        $pdf = $book->pdf_path;

        $this->put("/admin/buku/{$book->id}", $this->payload(['title' => 'Tanpa Ganti Berkas']));
        $this->assertSame($pdf, $book->fresh()->pdf_path, 'berkas lama tetap ada bila tidak ada unggahan');

        $this->put("/admin/buku/{$book->id}", $this->payload(['remove_pdf' => '1', 'remove_cover' => '1']));
        $book->refresh();
        $this->assertNull($book->pdf_path);
        $this->assertNull($book->cover_path);
        Storage::disk('local')->assertMissing($pdf);
    }

    public function test_deleting_a_book_deletes_its_files(): void
    {
        $this->actingAs($this->admin())->post('/admin/buku', $this->payload(['cover' => UploadedFile::fake()->image('a.png'), 'pdf' => $this->pdf()]));
        $book = Book::first();

        $this->delete("/admin/buku/{$book->id}")->assertRedirect(route('admin.books.index'))->assertSessionHas('success');

        $this->assertModelMissing($book);
        Storage::disk('local')->assertMissing($book->pdf_path);
        Storage::disk('public')->assertMissing($book->cover_path);
        $this->assertDatabaseHas('activity_logs', ['action' => 'delete_book']);
    }

    public function test_book_list_supports_search_and_category_filter(): void
    {
        $fiksi = Category::factory()->create(['name' => 'Fiksi', 'slug' => 'fiksi']);
        Book::factory()->for($fiksi)->create(['title' => 'Novel Satu']);
        Book::factory()->create(['title' => 'Buku Lain']);

        $this->actingAs($this->admin())->get('/admin/buku?q=novel')->assertSee('Novel Satu')->assertDontSee('Buku Lain');
        $this->get('/admin/buku?kategori=fiksi')->assertSee('Novel Satu')->assertDontSee('Buku Lain');
    }

    public function test_the_edit_form_uses_localised_urls(): void
    {
        $book = Book::factory()->create();

        $this->actingAs($this->admin())->get("/admin/buku/{$book->id}/ubah")->assertOk()->assertSee('Ubah buku');
        $this->assertSame(url("/admin/buku/{$book->id}/ubah"), route('admin.books.edit', $book));
    }
}
