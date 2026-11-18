<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            // Tarif di-snapshot saat meminjam agar perubahan tarif tidak mengubah tagihan lama.
            $table->unsignedInteger('fine_rate')->default(0);
            $table->unsignedSmallInteger('late_days')->default(0);
            $table->unsignedInteger('fine_amount')->default(0);
            $table->timestampTz('fine_paid_at')->nullable();
            $table->timestampTz('fine_waived_at')->nullable();
            $table->foreignId('fine_waived_by')->nullable()->constrained('users')->nullOnDelete();

            $table->index(['user_id', 'fine_paid_at']);
        });

        // Tagihan yang masih terbuka = dasar pemblokiran peminjaman.
        DB::statement(
            'CREATE INDEX loans_unpaid_fines ON loans (user_id)
             WHERE fine_amount > 0 AND fine_paid_at IS NULL AND fine_waived_at IS NULL'
        );

        Schema::table('users', function (Blueprint $table) {
            $table->timestampTz('suspend_until')->nullable();
            $table->index('suspend_until');
        });

        // Fitur denda hanya berlaku untuk pinjaman yang masih berjalan saat deploy.
        // Pinjaman lama yang sudah lewat tenggat ditutup tanpa denda agar tidak ada
        // pengguna yang tiba-tiba tertagih atas perilaku aplikasi versi lama.
        DB::table('loans')
            ->whereNull('returned_at')
            ->where('due_at', '<=', now())
            ->update(['returned_at' => DB::raw('due_at')]);

        // Pinjaman yang masih berjalan saat deploy belum punya snapshot tarif.
        // Isi dengan tarif aktif agar pengembalian telat setelah fitur aktif tetap
        // dihitung wajar, bukan Rp0. Pinjaman yang sudah telat sudah ditutup di atas.
        DB::table('loans')
            ->whereNull('returned_at')
            ->update(['fine_rate' => (int) config('library.fine.rate_per_day', 1000)]);
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS loans_unpaid_fines');

        Schema::table('loans', function (Blueprint $table) {
            $table->dropConstrainedForeignId('fine_waived_by');
            $table->dropIndex(['user_id', 'fine_paid_at']);
            $table->dropColumn(['fine_rate', 'late_days', 'fine_amount', 'fine_paid_at', 'fine_waived_at']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['suspend_until']);
            $table->dropColumn('suspend_until');
        });
    }
};
