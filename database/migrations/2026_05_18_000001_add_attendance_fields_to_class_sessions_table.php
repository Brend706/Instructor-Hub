<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Campos para el flujo de asistencia por QR en `class_sessions`.
 *
 * - public_token: identificador en la URL pública /asistencia/{token}
 * - session_code: código legible en pantalla del instructor (PROGRAMA-2026-004)
 * - is_open: si la sesión sigue aceptando registros de carnet
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('class_sessions', function (Blueprint $table) {
            if (! Schema::hasColumn('class_sessions', 'public_token')) {
                $table->string('public_token', 64)->nullable()->unique()->after('instructor_assignment_id');
            }
            if (! Schema::hasColumn('class_sessions', 'session_code')) {
                $table->string('session_code', 32)->nullable()->after('public_token');
            }
            if (! Schema::hasColumn('class_sessions', 'is_open')) {
                $table->boolean('is_open')->default(false)->after('session_code');
            }
        });
    }

    public function down(): void
    {
        Schema::table('class_sessions', function (Blueprint $table) {
            if (Schema::hasColumn('class_sessions', 'is_open')) {
                $table->dropColumn('is_open');
            }
            if (Schema::hasColumn('class_sessions', 'session_code')) {
                $table->dropColumn('session_code');
            }
            if (Schema::hasColumn('class_sessions', 'public_token')) {
                $table->dropColumn('public_token');
            }
        });
    }
};
