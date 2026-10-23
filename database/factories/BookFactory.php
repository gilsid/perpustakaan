<?php

namespace Database\Factories;

use App\Models\Book;
use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Storage;

/**
 * @extends Factory<Book>
 */
class BookFactory extends Factory
{
    public function definition(): array
    {
        return [
            'title' => fake()->unique()->sentence(3),
            'author' => fake()->name(),
            'category_id' => Category::factory(),
            'published_year' => fake()->numberBetween(1950, 2024),
            'description' => fake()->paragraph(),
            'language' => fake()->randomElement(['Indonesia', 'Inggris']),
        ];
    }

    /** Buku yang punya PDF sungguhan di disk privat (bisa dipinjam dan dibaca). */
    public function withPdf(): static
    {
        return $this->state(function () {
            $name = 'books/b_'.fake()->unique()->lexify('????????????').'.pdf';
            Storage::disk('local')->put($name, "%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF\n");

            return ['pdf_path' => $name];
        });
    }
}
