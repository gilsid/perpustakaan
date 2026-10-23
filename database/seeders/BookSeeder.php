<?php

namespace Database\Seeders;

use App\Models\Book;
use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class BookSeeder extends Seeder
{
    public function run(): void
    {
        $categories = Category::pluck('id', 'name');

        $books = [
            [
                'title' => 'The Pragmatic Programmer',
                'author' => 'Andrew Hunt, David Thomas',
                'published_year' => 1999,
                'category' => 'Programming',
                'description' => 'A practical guide to software craftsmanship, covering best practices, design principles, and pragmatic tips.',
            ],
            [
                'title' => 'Clean Code',
                'author' => 'Robert C. Martin',
                'published_year' => 2008,
                'category' => 'Programming',
                'description' => 'A handbook of agile software craftsmanship, focusing on writing readable, maintainable code.',
            ],
            [
                'title' => '1984',
                'author' => 'George Orwell',
                'published_year' => 1949,
                'category' => 'Fiction',
                'description' => 'A dystopian novel set in a totalitarian society under constant surveillance.',
            ],
            [
                'title' => 'To Kill a Mockingbird',
                'author' => 'Harper Lee',
                'published_year' => 1960,
                'category' => 'Fiction',
                'description' => 'A novel of racial injustice and moral growth in the American South.',
            ],
            [
                'title' => 'Sapiens: A Brief History of Humankind',
                'author' => 'Yuval Noah Harari',
                'published_year' => 2011,
                'category' => 'History',
                'description' => 'An exploration of the history and impact of Homo sapiens on the world.',
            ],
            [
                'title' => 'The Design of Everyday Things',
                'author' => 'Don Norman',
                'published_year' => 2013,
                'category' => 'Design',
                'description' => 'A classic on user-centered design and how everyday objects can be made more usable.',
            ],
            [
                'title' => 'The Mystery of Cabin Island',
                'author' => 'Franklin W Dixon',
                'published_year' => 2009,
                'category' => 'History',
                'description' => 'The Hardy Boys spend their winter holiday on Cabin Island, where a stolen boat and a bitter rival lead them into a puzzling mystery.',
            ],
        ];

        foreach ($books as $row) {
            $category = $row['category'];
            unset($row['category']);

            Book::firstOrCreate(
                ['title' => $row['title'], 'author' => $row['author']],
                $row + ['category_id' => $categories[$category]],
            );
        }

        // Satu buku contoh punya PDF, supaya alur pinjam dan baca bisa langsung dicoba.
        $sample = Book::where('title', 'The Mystery of Cabin Island')->first();
        if ($sample && ! $sample->pdf_path) {
            $name = 'books/b_'.Str::lower(Str::random(16)).'.pdf';
            Storage::disk('local')->put($name, (string) file_get_contents(database_path('seeders/files/the-mystery-of-cabin-island.pdf')));
            $sample->update(['pdf_path' => $name]);
        }
    }
}
