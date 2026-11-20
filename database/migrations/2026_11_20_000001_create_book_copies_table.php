<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('book_copies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('book_id')->constrained()->cascadeOnDelete();
            $table->string('code', 40)->unique();
            $table->string('location', 80)->nullable();
            $table->string('status', 20)->default('available');
            $table->timestampsTz();

            $table->index(['book_id', 'status']);
        });

        DB::statement(
            "ALTER TABLE book_copies ADD CONSTRAINT book_copies_status_check
             CHECK (status IN ('available', 'loan', 'reserved', 'lost', 'damaged', 'withdrawn'))"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('book_copies');
    }
};
