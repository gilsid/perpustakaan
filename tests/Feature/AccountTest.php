<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_name_can_be_changed_without_a_password(): void
    {
        $user = $this->member(['name' => 'Lama', 'email' => 'tetap@example.com']);

        $this->actingAs($user)->put('/profil', ['name' => 'Baru', 'email' => 'tetap@example.com'])->assertSessionHas('success');

        $this->assertSame('Baru', $user->fresh()->name);
    }

    public function test_changing_email_requires_the_current_password(): void
    {
        $user = $this->member(['email' => 'lama@example.com']);

        $this->actingAs($user)->put('/profil', ['name' => $user->name, 'email' => 'baru@example.com', 'email_password' => 'salah'])
            ->assertSessionHasErrors('email_password');
        $this->assertSame('lama@example.com', $user->fresh()->email);

        $this->put('/profil', ['name' => $user->name, 'email' => 'Baru@Example.com', 'email_password' => 'password'])->assertSessionHas('success');
        $this->assertSame('baru@example.com', $user->fresh()->email);
    }

    public function test_email_must_be_unique_among_other_users(): void
    {
        $this->member(['email' => 'dipakai@example.com']);
        $user = $this->member();

        $this->actingAs($user)->put('/profil', ['name' => 'X', 'email' => 'dipakai@example.com', 'email_password' => 'password'])
            ->assertSessionHasErrors('email');
    }

    public function test_password_change_requires_the_current_password_and_a_new_one(): void
    {
        $user = $this->member();
        $this->actingAs($user);

        $this->put('/profil/sandi', ['current_password' => 'salah', 'password' => 'sandibaru123', 'password_confirmation' => 'sandibaru123'])
            ->assertSessionHasErrors('current_password', errorBag: 'password');
        $this->put('/profil/sandi', ['current_password' => 'password', 'password' => 'password', 'password_confirmation' => 'password'])
            ->assertSessionHasErrors('password', errorBag: 'password');
        $this->put('/profil/sandi', ['current_password' => 'password', 'password' => 'pendek', 'password_confirmation' => 'pendek'])
            ->assertSessionHasErrors('password', errorBag: 'password');

        $this->put('/profil/sandi', ['current_password' => 'password', 'password' => 'sandibaru123', 'password_confirmation' => 'sandibaru123'])
            ->assertSessionHas('success');
        $this->assertTrue(Hash::check('sandibaru123', $user->fresh()->password));
    }

    public function test_admin_profile_page_has_no_member_tabs(): void
    {
        $this->actingAs($this->admin())->get('/profil')->assertOk()->assertSee('Profil admin')->assertDontSee('Pinjaman</a>', false);
    }
}
