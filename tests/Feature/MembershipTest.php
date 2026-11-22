<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Loan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MembershipTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_admin_can_update_a_member_card_and_expiry(): void
    {
        $member = $this->member();

        $this->actingAs($this->admin())->patch("/admin/pengguna/{$member->id}/kartu", [
            'member_number' => 'MP-CUSTOM',
            'membership_expires_at' => now()->addYear()->toDateString(),
        ])->assertRedirect(route('admin.users.index'));

        $this->assertSame('MP-CUSTOM', $member->fresh()->member_number);
        $this->assertNotNull($member->fresh()->membership_expires_at);
        $this->assertDatabaseHas('activity_logs', ['action' => 'membership_updated', 'entity_id' => $member->id]);
    }

    public function test_an_expired_membership_blocks_borrowing(): void
    {
        $member = $this->member(['membership_expires_at' => now()->subDay()]);
        $book = Book::factory()->withPdf()->create();

        $this->actingAs($member)->post("/buku/{$book->id}/pinjam")
            ->assertSessionHas('error')
            ->assertSessionMissing('success');

        $this->assertSame(0, Loan::count());
    }

    public function test_a_member_sees_their_number_on_the_profile(): void
    {
        $member = $this->member(['member_number' => 'MP-PROFIL']);

        $this->actingAs($member)->get(route('account.profile'))
            ->assertOk()->assertSee('MP-PROFIL');
    }

    public function test_the_home_page_no_longer_says_a_card_is_unneeded(): void
    {
        $this->get('/')->assertOk()
            ->assertSee('Nomor anggota dibuat otomatis')
            ->assertDontSee('Tidak perlu kartu anggota');
    }

    public function test_member_numbers_must_be_unique(): void
    {
        $member = $this->member(['member_number' => 'MP-SAMA']);

        $this->actingAs($this->admin())->patch("/admin/pengguna/{$member->id}/kartu", [
            'member_number' => $this->member(['member_number' => 'MP-LAIN'])->member_number,
            'membership_expires_at' => '',
        ])->assertSessionHasErrors('member_number');
    }
}
