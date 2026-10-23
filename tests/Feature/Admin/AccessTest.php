<?php

namespace Tests\Feature\Admin;

use App\Models\Loan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccessTest extends TestCase
{
    use RefreshDatabase;

    private const PAGES = ['/admin', '/admin/buku', '/admin/buku/baru', '/admin/kategori', '/admin/peminjaman', '/admin/ulasan', '/admin/pengguna', '/admin/log'];

    public function test_members_get_403_on_every_admin_page(): void
    {
        $this->actingAs($this->member());

        foreach (self::PAGES as $page) {
            $this->get($page)->assertForbidden();
        }
    }

    public function test_guests_are_redirected_to_login(): void
    {
        foreach (self::PAGES as $page) {
            $this->get($page)->assertRedirect(route('login'));
        }
    }

    public function test_admins_can_open_every_admin_page(): void
    {
        $this->actingAs($this->admin());

        foreach (self::PAGES as $page) {
            $this->get($page)->assertOk();
        }
    }

    public function test_dashboard_shows_the_figures(): void
    {
        Loan::factory()->create();

        $this->actingAs($this->admin())->get('/admin')->assertOk()
            ->assertSee('Pinjaman aktif')->assertSee('Peminjaman 14 hari terakhir')->assertSee('Paling sering dipinjam');
    }
}
