<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        foreach (['Programming', 'Fiction', 'History', 'Design', 'Science', 'Adventure', 'Comedy'] as $name) {
            Category::firstOrCreate(['name' => $name]);
        }
    }
}
