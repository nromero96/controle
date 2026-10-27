<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->unsignedSmallInteger('overtime_minimum_minutes')->nullable();
            $table->unsignedSmallInteger('reference_lunch_minutes')->nullable();
            $table->string('overtime_status', 20)->nullable();
            $table->unsignedInteger('approved_overtime_seconds')->nullable();
            $table->foreignId('overtime_reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('overtime_reviewed_at')->nullable();
            $table->string('overtime_review_note', 500)->nullable();
            $table->string('overtime_fingerprint', 64)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropForeign(['overtime_reviewed_by']);
            $table->dropColumn(['overtime_minimum_minutes', 'reference_lunch_minutes', 'overtime_status', 'approved_overtime_seconds', 'overtime_reviewed_by', 'overtime_reviewed_at', 'overtime_review_note', 'overtime_fingerprint']);
        });
    }
};
