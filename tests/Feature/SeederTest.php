<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeded_accounts_have_member_numbers(): void
    {
        $this->seed(UserSeeder::class);

        $this->assertNotNull(User::where('email', 'admin@example.com')->firstOrFail()->member_number);
        $this->assertNotNull(User::where('email', 'user@example.com')->firstOrFail()->member_number);
    }
}
