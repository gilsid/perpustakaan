<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE book_copies DROP CONSTRAINT IF EXISTS book_copies_status_check');
        DB::table('book_copies')->where('status', 'reserved')->update(['status' => 'on_hold']);
        DB::statement(
            "ALTER TABLE book_copies ADD CONSTRAINT book_copies_status_check
             CHECK (status IN ('available', 'loan', 'on_hold', 'lost', 'damaged', 'withdrawn'))"
        );
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE book_copies DROP CONSTRAINT IF EXISTS book_copies_status_check');
        DB::table('book_copies')->where('status', 'on_hold')->update(['status' => 'reserved']);
        DB::statement(
            "ALTER TABLE book_copies ADD CONSTRAINT book_copies_status_check
             CHECK (status IN ('available', 'loan', 'reserved', 'lost', 'damaged', 'withdrawn'))"
        );
    }
};
