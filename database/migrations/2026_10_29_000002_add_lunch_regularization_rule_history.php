<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_lunch_adjustments', fn (Blueprint $table) => $table->json('previous_lunch_rules')->nullable());
    }

    public function down(): void
    {
        Schema::table('attendance_lunch_adjustments', fn (Blueprint $table) => $table->dropColumn('previous_lunch_rules'));
    }
};
