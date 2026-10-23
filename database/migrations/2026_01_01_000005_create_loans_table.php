<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('book_id')->constrained()->cascadeOnDelete();
            $table->timestampTz('borrowed_at')->useCurrent();
            $table->timestampTz('due_at');
            $table->timestampTz('returned_at')->nullable();
            $table->unsignedSmallInteger('renewals')->default(0);

            $table->index(['user_id', 'borrowed_at']);
            $table->index('borrowed_at');
        });

        DB::statement('ALTER TABLE loans ADD CONSTRAINT loans_due_check CHECK (due_at > borrowed_at)');

        // Mencegah pinjaman ganda yang masih berjalan untuk anggota + buku yang sama.
        // Dijaga di level database agar aman dari race condition.
        DB::statement('CREATE UNIQUE INDEX loans_one_open ON loans (user_id, book_id) WHERE returned_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('loans');
    }
};
