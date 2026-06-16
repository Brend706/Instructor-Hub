<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suspension_requests', function (Blueprint $table) {
            $table->id();

            $table->foreignId('instructor_id')
                ->constrained('instructors')
                ->cascadeOnDelete();

            // Asignación afectada (siempre la activa al momento de la solicitud).
            $table->foreignId('assignment_id')
                ->nullable()
                ->constrained('instructor_assignments')
                ->nullOnDelete();

            // Tipo de solicitud elegido por el instructor.
            $table->string('type')->default('voluntary');
            // voluntary | force_majeure | other

            // Motivo detallado escrito por el instructor.
            $table->text('reason');

            // Estado de la revisión.
            $table->string('status')->default('pending');
            // pending | approved | rejected

            // Quién revisó (coordinador o admin).
            $table->foreignId('reviewed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            // Nota interna del revisor (no necesariamente visible al instructor).
            $table->text('admin_notes')->nullable();

            $table->timestamp('requested_at')->useCurrent();
            $table->timestamp('reviewed_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('suspension_requests');
    }
};
