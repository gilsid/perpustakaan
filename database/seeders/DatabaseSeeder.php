<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Data awal: kategori, 7 buku contoh, 2 akun contoh, serta satu ulasan/favorit/pinjaman.
     * Aman dijalankan ulang (tidak membuat duplikat).
     */
    public function run(): void
    {
        $this->call([
            CategorySeeder::class,
            UserSeeder::class,
            BookSeeder::class,
            ReaderActivitySeeder::class,
        ]);
    }
}
