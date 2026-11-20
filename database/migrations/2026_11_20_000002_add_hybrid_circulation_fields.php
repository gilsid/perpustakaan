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
            $table->string('format', 10)->default('digital')->after('book_id');
            $table->foreignId('copy_id')->nullable()->after('format')->constrained('book_copies')->nullOnDelete();
        });

        DB::statement(
            "ALTER TABLE loans ADD CONSTRAINT loans_format_check CHECK (format IN ('digital', 'fisik'))"
        );
        DB::statement(
            'CREATE UNIQUE INDEX loans_one_open_copy ON loans (copy_id)
             WHERE returned_at IS NULL AND copy_id IS NOT NULL'
        );

        Schema::table('users', function (Blueprint $table) {
            $table->string('member_number', 30)->nullable()->unique()->after('email');
            $table->timestampTz('membership_expires_at')->nullable()->after('member_number');
        });

        // Akun lama tetap bisa dipakai: nomor kartu diisi otomatis dari id.
        DB::table('users')->whereNull('member_number')->orderBy('id')->get()->each(function (object $user): void {
            DB::table('users')->where('id', $user->id)->update([
                'member_number' => 'MP-'.str_pad((string) $user->id, 6, '0', STR_PAD_LEFT),
            ]);
        });
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS loans_one_open_copy');
        DB::statement('ALTER TABLE loans DROP CONSTRAINT IF EXISTS loans_format_check');

        Schema::table('loans', function (Blueprint $table) {
            $table->dropConstrainedForeignId('copy_id');
            $table->dropColumn('format');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['member_number']);
            $table->dropColumn(['member_number', 'membership_expires_at']);
        });
    }
};
