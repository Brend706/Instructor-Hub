<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agrega la columna `admin_student_verdict` a `instructor_assignments`.
 * Almacena las observaciones del administrador sobre el conjunto de
 * evaluaciones de estudiantes de un assignment específico.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('instructor_assignments', function (Blueprint $table) {
            $table->text('admin_student_verdict')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('instructor_assignments', function (Blueprint $table) {
            $table->dropColumn('admin_student_verdict');
        });
    }
};
