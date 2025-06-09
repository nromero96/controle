<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->string('day_name')->nullable()->after('mark_date');
            $table->time('scheduled_start_time')->nullable()->after('day_name');
            $table->time('scheduled_end_time')->nullable()->after('scheduled_start_time');
            $table->time('scheduled_break_start')->nullable()->after('scheduled_end_time');
            $table->time('scheduled_break_end')->nullable()->after('scheduled_break_start');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropColumn('day_name');
            $table->dropColumn('scheduled_start_time');
            $table->dropColumn('scheduled_end_time');
            $table->dropColumn('scheduled_break_start');
            $table->dropColumn('scheduled_break_end');
        });
    }
};
