<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->time('corrected_time')->nullable();
            $table->boolean('is_manual')->default(false);
            $table->unsignedInteger('correction_version')->default(0);
        });
        Schema::create('attendance_time_corrections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attendance_id')->constrained()->cascadeOnDelete();
            $table->time('previous_time')->nullable();
            $table->time('corrected_time');
            $table->string('reason', 1000);
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('previous_overtime_review')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_time_corrections');
        Schema::table('attendances', fn (Blueprint $table) => $table->dropColumn(['corrected_time', 'is_manual', 'correction_version']));
    }
};
