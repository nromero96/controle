<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->unsignedSmallInteger('minimum_lunch_minutes')->nullable();
            $table->unsignedSmallInteger('maximum_lunch_minutes')->nullable();
        });
        Schema::create('attendance_lunch_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attendance_id')->constrained('attendances')->cascadeOnDelete();
            $table->time('lunch_start');
            $table->time('lunch_end');
            $table->string('reason', 1000);
            $table->json('previous_overtime_review')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('source_fingerprint', 64);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_lunch_adjustments');
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropColumn(['minimum_lunch_minutes', 'maximum_lunch_minutes']);
        });
    }
};
