<?php

namespace Database\Factories;

use App\Models\Book;
use App\Models\Loan;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Loan>
 */
class LoanFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'book_id' => Book::factory()->withPdf(),
            'borrowed_at' => now()->subDay(),
            'due_at' => now()->addDays(13),
        ];
    }

    public function expired(): static
    {
        return $this->state(fn () => ['borrowed_at' => now()->subDays(20), 'due_at' => now()->subDays(6)]);
    }

    public function returned(): static
    {
        return $this->state(fn () => ['returned_at' => now()]);
    }
}
