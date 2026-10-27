<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            // Identidad histórica: se conserva incluso si la sede se elimina.
            $table->unsignedBigInteger('recorded_branch_id')->nullable()->index();
            $table->string('recorded_branch_name')->nullable();
            $table->boolean('branch_snapshot_captured')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropIndex(['recorded_branch_id']);
            $table->dropColumn(['recorded_branch_id', 'recorded_branch_name', 'branch_snapshot_captured']);
        });
    }
};
