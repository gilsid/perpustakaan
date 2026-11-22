<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('book_copies', function (Blueprint $table) {
            $table->bigInteger('replacement_cost')->nullable()->after('location');
        });

        Schema::table('loans', function (Blueprint $table) {
            $table->bigInteger('replacement_amount')->default(0)->after('fine_waived_by');
            $table->timestampTz('replacement_paid_at')->nullable()->after('replacement_amount');
            $table->timestampTz('replacement_waived_at')->nullable()->after('replacement_paid_at');
            $table->foreignId('replacement_waived_by')->nullable()->after('replacement_waived_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->dropConstrainedForeignId('replacement_waived_by');
            $table->dropColumn(['replacement_amount', 'replacement_paid_at', 'replacement_waived_at']);
        });

        Schema::table('book_copies', function (Blueprint $table) {
            $table->dropColumn('replacement_cost');
        });
    }
};
