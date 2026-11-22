<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('holds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('book_id')->constrained()->cascadeOnDelete();
            $table->foreignId('copy_id')->nullable()->constrained('book_copies')->nullOnDelete();
            $table->foreignId('loan_id')->nullable()->constrained('loans')->nullOnDelete();
            $table->string('status', 20)->default('aktif');
            $table->timestampTz('ready_at')->nullable();
            $table->timestampTz('pickup_expires_at')->nullable();
            $table->timestampTz('fulfilled_at')->nullable();
            $table->timestampsTz();

            $table->index(['book_id', 'status']);
            $table->index(['user_id', 'status']);
        });

        DB::statement(
            "ALTER TABLE holds ADD CONSTRAINT holds_status_check
             CHECK (status IN ('aktif', 'siap_diambil', 'dipenuhi', 'kedaluwarsa', 'dibatalkan'))"
        );
        DB::statement(
            "CREATE UNIQUE INDEX holds_one_pending ON holds (user_id, book_id)
             WHERE status IN ('aktif', 'siap_diambil')"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('holds');
    }
};
