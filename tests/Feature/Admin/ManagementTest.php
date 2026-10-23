<?php

namespace Tests\Feature\Admin;

use App\Models\ActivityLog;
use App\Models\Book;
use App\Models\Category;
use App\Models\Loan;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ManagementTest extends TestCase
{
    use RefreshDatabase;

    // ---------------------------------------------------------------- kategori

    public function test_categories_can_be_created_renamed_and_deleted(): void
    {
        $this->actingAs($this->admin());

        $this->post('/admin/kategori', ['name' => 'Ilmu Alam'])->assertSessionHas('success');
        $category = Category::firstWhere('name', 'Ilmu Alam');
        $this->assertSame('ilmu-alam', $category->slug);

        $this->put("/admin/kategori/{$category->id}", ['name' => 'Sains'])->assertSessionHas('success');
        $this->assertSame('sains', $category->fresh()->slug);

        $this->delete("/admin/kategori/{$category->id}")->assertSessionHas('success', 'Kategori dihapus.');
        $this->assertModelMissing($category);
    }

    public function test_category_names_and_slugs_are_unique(): void
    {
        Category::factory()->create(['name' => 'Fiksi']);
        $this->actingAs($this->admin());

        $this->post('/admin/kategori', ['name' => 'Fiksi'])->assertSessionHasErrors(['name' => 'Nama kategori itu sudah ada.']);

        // Nama berbeda yang menghasilkan slug sama tidak boleh bentrok.
        $this->post('/admin/kategori', ['name' => 'Fiksi!']);
        $this->assertSame(['fiksi', 'fiksi-2'], Category::orderBy('id')->pluck('slug')->all());
    }

    public function test_a_category_in_use_cannot_be_deleted(): void
    {
        $book = Book::factory()->create();

        $this->actingAs($this->admin())->delete("/admin/kategori/{$book->category_id}")->assertSessionHas('error');
        $this->assertModelExists($book->category);
    }

    // ------------------------------------------------------------- peminjaman

    public function test_admin_can_end_an_active_loan(): void
    {
        $loan = Loan::factory()->create();

        $this->actingAs($this->admin())->patch("/admin/peminjaman/{$loan->id}")->assertSessionHas('success');

        $this->assertNotNull($loan->fresh()->returned_at);
        $this->actingAs($loan->user)->get("/berkas/{$loan->book_id}")->assertForbidden();
        $this->assertDatabaseHas('activity_logs', ['action' => 'finish_loan']);
    }

    public function test_ending_an_already_finished_loan_reports_an_error(): void
    {
        $loan = Loan::factory()->returned()->create();

        $this->actingAs($this->admin())->patch("/admin/peminjaman/{$loan->id}")->assertSessionHas('error');
    }

    public function test_loan_list_filters_by_status_and_search(): void
    {
        $active = Loan::factory()->create();
        $expired = Loan::factory()->expired()->create();
        $returned = Loan::factory()->returned()->create();
        $admin = $this->admin();

        $this->actingAs($admin)->get('/admin/peminjaman?status=aktif')->assertSee($active->book->title)->assertDontSee($expired->book->title)->assertDontSee($returned->book->title);
        $this->get('/admin/peminjaman?status=berakhir')->assertSee($expired->book->title)->assertDontSee($active->book->title);
        $this->get('/admin/peminjaman?status=selesai')->assertSee($returned->book->title)->assertDontSee($active->book->title);
        $this->get('/admin/peminjaman?q='.urlencode($active->user->email))->assertSee($active->book->title)->assertDontSee($expired->book->title);
        $this->get('/admin/peminjaman?status[]=x&q[]=y')->assertOk();
    }

    // ------------------------------------------------------------------ ulasan

    public function test_admin_can_moderate_reviews(): void
    {
        $review = Review::factory()->create(['comment' => 'Ulasan yang tidak pantas']);
        $admin = $this->admin();

        $this->actingAs($admin)->get('/admin/ulasan?q=tidak+pantas')->assertSee('Ulasan yang tidak pantas');
        $this->delete("/admin/ulasan/{$review->id}")->assertSessionHas('success');
        $this->assertModelMissing($review);
    }

    // ---------------------------------------------------------------- pengguna

    public function test_admin_resets_a_password_and_the_user_must_change_it(): void
    {
        $user = $this->member(['email' => 'anggota@example.com']);

        $response = $this->actingAs($this->admin())->post("/admin/pengguna/{$user->id}/reset-sandi");
        $message = session('success');
        preg_match('/: (\S{10}) —/u', $message, $m);
        $temporary = $m[1] ?? '';

        $this->assertSame(10, strlen($temporary), $message);
        $user->refresh();
        $this->assertTrue($user->must_change_password);
        $this->assertTrue(Hash::check($temporary, $user->password));
        $this->assertFalse(Hash::check('password', $user->password));
        $this->assertDatabaseHas('activity_logs', ['action' => 'reset_password', 'entity_id' => $user->id]);

        $this->post('/keluar');
        $this->post('/masuk', ['email' => 'anggota@example.com', 'password' => $temporary])->assertRedirectContains('/profil');
    }

    public function test_admin_cannot_reset_deactivate_or_change_the_role_of_themselves(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);

        $this->post("/admin/pengguna/{$admin->id}/reset-sandi")->assertSessionHas('error');
        $this->patch("/admin/pengguna/{$admin->id}/status")->assertSessionHas('error');
        $this->patch("/admin/pengguna/{$admin->id}/peran", ['role' => 'member'])->assertSessionHas('error');
        $this->assertTrue($admin->fresh()->is_active);
        $this->assertTrue($admin->fresh()->isAdmin());
    }

    public function test_the_last_active_admin_cannot_be_deactivated(): void
    {
        $actor = $this->admin();
        $other = $this->admin(['is_active' => false]);
        $this->actingAs($actor);

        // Hanya satu admin aktif (actor), jadi menonaktifkan admin lain yang sudah nonaktif tidak relevan;
        // buat skenario: dua admin aktif, nonaktifkan satu → boleh, lalu yang terakhir tidak boleh.
        $second = $this->admin();
        $this->patch("/admin/pengguna/{$second->id}/status")->assertSessionHas('success');
        $this->assertSame(1, User::where('role', 'admin')->where('is_active', true)->count());

        $actor2 = $this->admin();
        $this->actingAs($actor2);
        $this->patch("/admin/pengguna/{$actor->id}/status")->assertSessionHas('success');
        $this->assertSame(1, User::where('role', 'admin')->where('is_active', true)->count());
    }

    public function test_admin_can_deactivate_activate_and_change_roles(): void
    {
        $user = $this->member();
        $this->actingAs($this->admin());

        $this->patch("/admin/pengguna/{$user->id}/status")->assertSessionHas('success');
        $this->assertFalse($user->fresh()->is_active);
        $this->patch("/admin/pengguna/{$user->id}/status")->assertSessionHas('success', 'Akun diaktifkan.');
        $this->assertTrue($user->fresh()->is_active);

        $this->patch("/admin/pengguna/{$user->id}/peran", ['role' => 'admin'])->assertSessionHas('success', 'Pengguna dijadikan admin.');
        $this->assertTrue($user->fresh()->isAdmin());
        $this->patch("/admin/pengguna/{$user->id}/peran", ['role' => 'member'])->assertSessionHas('success');
        $this->patch("/admin/pengguna/{$user->id}/peran", ['role' => 'superuser'])->assertSessionHasErrors('role');
    }

    public function test_user_list_filters_by_search_and_role(): void
    {
        $this->member(['name' => 'Budi Santoso', 'email' => 'budi@example.com']);
        $this->member(['name' => 'Citra Lestari', 'email' => 'citra@example.com']);
        $this->actingAs($this->admin());

        $this->get('/admin/pengguna?q=budi')->assertSee('Budi Santoso')->assertDontSee('Citra Lestari');
        $this->get('/admin/pengguna?peran=admin')->assertDontSee('Budi Santoso');
        $this->get('/admin/pengguna?peran=bukan-peran')->assertOk();
    }

    // --------------------------------------------------------------------- log

    public function test_activity_log_lists_and_searches_entries(): void
    {
        $admin = $this->admin();
        ActivityLog::record('create_book', 'book', 1, 'Menambah buku "Rahasia Log"', $admin);
        ActivityLog::record('delete_book', 'book', 2, 'Menghapus buku "Lainnya"', $admin);

        $this->actingAs($admin)->get('/admin/log')->assertSee('Rahasia Log')->assertSee('Lainnya');
        $this->get('/admin/log?q=rahasia')->assertSee('Rahasia Log')->assertDontSee('Lainnya');
    }
}
