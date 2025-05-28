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
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->onDelete('cascade');
            $table->enum('mark_type', ['entry', 'break_out', 'break_in', 'exit']);
            $table->date('mark_date');
            $table->time('mark_time');
            $table->string('ip_address')->nullable();
            $table->string('device')->nullable();
            $table->string('photo')->nullable();
            $table->timestamps();

            // Opcional: evitar que un empleado registre dos veces el mismo tipo en el mismo día
            $table->unique(['employee_id', 'mark_type', 'mark_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendances');
    }
};
