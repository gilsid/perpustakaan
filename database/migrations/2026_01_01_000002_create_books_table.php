<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('books', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('author');
            $table->string('isbn', 20)->nullable();
            $table->string('publisher', 160)->nullable();
            $table->smallInteger('published_year')->nullable();
            $table->string('language', 40)->nullable();
            $table->unsignedInteger('pages')->nullable();
            $table->string('call_number', 40)->nullable();
            $table->foreignId('category_id')->nullable()->constrained()->restrictOnDelete();
            $table->text('description')->default('');
            $table->string('cover_path')->nullable();
            $table->string('pdf_path')->nullable();
            $table->timestampsTz();

            $table->index('created_at');
            $table->index('published_year');
        });

        DB::statement('ALTER TABLE books ADD CONSTRAINT books_year_check CHECK (published_year BETWEEN 1000 AND 2200)');
        DB::statement('ALTER TABLE books ADD CONSTRAINT books_pages_check CHECK (pages > 0)');

        // Indeks pencarian teks (pg_trgm). Opsional: bila ekstensi tidak bisa dipasang,
        // pencarian tetap berfungsi, hanya lebih lambat pada katalog yang sangat besar.
        DB::unprepared(<<<'SQL'
        DO $$
        BEGIN
            CREATE EXTENSION IF NOT EXISTS pg_trgm;
            CREATE INDEX IF NOT EXISTS books_title_trgm  ON books USING gin (title  gin_trgm_ops);
            CREATE INDEX IF NOT EXISTS books_author_trgm ON books USING gin (author gin_trgm_ops);
        EXCEPTION WHEN OTHERS THEN
            RAISE NOTICE 'pg_trgm tidak tersedia, indeks pencarian dilewati: %', SQLERRM;
        END $$;
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('books');
    }
};
