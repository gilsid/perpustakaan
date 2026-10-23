<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CreateAdminCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_an_admin(): void
    {
        $this->artisan('library:create-admin', ['name' => 'Pustakawan', 'email' => 'Pustakawan@Contoh.id', 'password' => 'kata-sandi-kuat'])->assertSuccessful();

        $user = User::firstWhere('email', 'pustakawan@contoh.id');
        $this->assertTrue($user->isAdmin());
        $this->assertTrue(Hash::check('kata-sandi-kuat', $user->password));
    }

    public function test_it_promotes_an_existing_account(): void
    {
        $user = $this->member(['email' => 'ada@contoh.id', 'is_active' => false]);

        $this->artisan('library:create-admin', ['name' => 'X', 'email' => 'ada@contoh.id', 'password' => 'kata-sandi-kuat'])->assertSuccessful();

        $this->assertTrue($user->fresh()->isAdmin());
        $this->assertTrue($user->fresh()->is_active);
        $this->assertSame(1, User::count());
    }

    public function test_it_rejects_invalid_input(): void
    {
        $this->artisan('library:create-admin', ['name' => 'X', 'email' => 'bukan-email', 'password' => 'kata-sandi-kuat'])->assertFailed();
        $this->artisan('library:create-admin', ['name' => 'X', 'email' => 'a@b.id', 'password' => '123'])->assertFailed();
        $this->assertSame(0, User::count());
    }
}
