<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_and_register_pages_render_for_guests(): void
    {
        $this->get('/masuk')->assertOk()->assertSee('Masuk');
        $this->get('/daftar')->assertOk()->assertSee('Buat akun');
    }

    public function test_protected_pages_redirect_guests_to_login(): void
    {
        foreach (['/admin', '/pinjaman', '/favorit', '/profil', '/baca/1', '/berkas/1'] as $path) {
            $this->get($path)->assertRedirect(route('login'));
        }
    }

    public function test_registration_creates_a_member_and_logs_in(): void
    {
        $this->post('/daftar', ['name' => 'Penguji', 'email' => 'Penguji@Example.com', 'password' => 'rahasia123', 'password_confirmation' => 'rahasia123'])
            ->assertRedirect(route('home'));

        $user = User::firstWhere('email', 'penguji@example.com');
        $this->assertNotNull($user, 'email disimpan huruf kecil');
        $this->assertTrue($user->isMember());
        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->last_login_at, 'pendaftaran juga dicatat sebagai masuk');
        $this->assertDatabaseHas('activity_logs', ['action' => 'register', 'user_id' => $user->id]);
    }

    public function test_registration_validates_input(): void
    {
        $this->post('/daftar', ['name' => 'X', 'email' => 'x@example.com', 'password' => 'pendek', 'password_confirmation' => 'pendek'])
            ->assertSessionHasErrors('password');
        $this->post('/daftar', ['name' => 'X', 'email' => 'x@example.com', 'password' => 'rahasia123', 'password_confirmation' => 'beda12345'])
            ->assertSessionHasErrors('password');
        $this->post('/daftar', ['name' => '', 'email' => 'bukan-email', 'password' => 'rahasia123', 'password_confirmation' => 'rahasia123'])
            ->assertSessionHasErrors(['name', 'email']);
        $this->assertGuest();
    }

    public function test_duplicate_email_is_rejected_regardless_of_case(): void
    {
        User::factory()->create(['email' => 'sama@example.com']);

        $this->post('/daftar', ['name' => 'Dobel', 'email' => 'SAMA@example.com', 'password' => 'rahasia123', 'password_confirmation' => 'rahasia123'])
            ->assertSessionHasErrors('email');
    }

    public function test_user_names_are_escaped_in_the_interface(): void
    {
        $user = $this->member(['name' => 'Penguji <b>tebal</b>']);

        $this->actingAs($user)->get('/profil')->assertSee('Penguji &lt;b&gt;tebal&lt;/b&gt;', false)->assertDontSee('<b>tebal</b>', false);
    }

    public function test_members_and_admins_are_sent_to_their_own_home(): void
    {
        $member = $this->member(['email' => 'm@example.com']);
        $admin = $this->admin(['email' => 'a@example.com']);

        $this->post('/masuk', ['email' => 'm@example.com', 'password' => 'password'])->assertRedirect(route('home'));
        $this->post('/keluar');
        $this->post('/masuk', ['email' => 'A@Example.com', 'password' => 'password'])->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($admin);
        $this->assertNotNull($admin->fresh()->last_login_at);
    }

    public function test_wrong_credentials_and_inactive_accounts_cannot_log_in(): void
    {
        $this->member(['email' => 'm@example.com']);
        $this->member(['email' => 'off@example.com', 'is_active' => false]);

        $this->post('/masuk', ['email' => 'm@example.com', 'password' => 'salah'])->assertSessionHasErrors('form');
        $this->post('/masuk', ['email' => 'tidak@ada.id', 'password' => 'salah'])->assertSessionHasErrors('form');
        $this->post('/masuk', ['email' => 'off@example.com', 'password' => 'password'])->assertSessionHasErrors('form');
        $this->assertGuest();
    }

    public function test_login_is_throttled_after_repeated_failures(): void
    {
        $this->member(['email' => 'korban@example.com']);

        for ($i = 0; $i < 5; $i++) {
            $this->post('/masuk', ['email' => 'korban@example.com', 'password' => 'salah-terus']);
        }
        // Percobaan ke-6 dikunci, bahkan dengan sandi yang benar.
        $this->post('/masuk', ['email' => 'korban@example.com', 'password' => 'password'])
            ->assertSessionHasErrors(['form' => 'Terlalu banyak percobaan masuk. Coba lagi dalam 15 menit.']);
        $this->assertGuest();
    }

    public function test_next_parameter_only_accepts_internal_paths(): void
    {
        $user = $this->member(['email' => 'm@example.com']);

        $this->get('/masuk?next=//evil.com');
        $this->post('/masuk', ['email' => 'm@example.com', 'password' => 'password'])
            ->assertRedirect(route('home'));
        $this->post('/keluar');

        $this->get('/masuk?next=/katalog');
        $this->post('/masuk', ['email' => 'm@example.com', 'password' => 'password'])
            ->assertRedirect(url('/katalog'));
    }

    public function test_logout_requires_post_and_ends_the_session(): void
    {
        $this->actingAs($this->member())->get('/keluar')->assertStatus(405);
        $this->post('/keluar')->assertRedirect(route('home'));
        $this->assertGuest();
    }

    public function test_deactivated_accounts_are_logged_out_immediately(): void
    {
        $user = $this->member();
        $this->actingAs($user)->get('/pinjaman')->assertOk();

        $user->update(['is_active' => false]);

        $this->get('/pinjaman')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_temporary_password_forces_a_password_change(): void
    {
        $user = $this->member(['must_change_password' => true]);

        $this->actingAs($user)->get('/pinjaman')->assertRedirectContains('/profil');
        $this->get('/profil')->assertOk();

        $this->put('/profil/sandi', ['current_password' => 'password', 'password' => 'sandibaru123', 'password_confirmation' => 'sandibaru123'])
            ->assertRedirect(route('account.profile'));

        $this->assertFalse($user->fresh()->must_change_password);
        $this->get('/pinjaman')->assertOk();
    }
}
