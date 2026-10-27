<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->boolean('is_active')->default(true);
        });
        Schema::table('employees', function (Blueprint $table) {
            $table->dropForeign(['branch_id']);
            $table->foreign('branch_id')->references('id')->on('branches')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropForeign(['branch_id']);
            $table->foreign('branch_id')->references('id')->on('branches')->nullOnDelete();
        });
        Schema::table('branches', function (Blueprint $table) {
            $table->dropColumn('is_active');
        });
    }
};
