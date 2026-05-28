<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cada grupo (`class_groups`) ahora pertenece a un coordinador.
 *
 * - Se agrega como nullable para no romper grupos creados antes de este cambio.
 *   El admin podrá reasignarlos manualmente desde phpMyAdmin o desde una vista
 *   futura. Los grupos que queden con coordinator_id NULL no aparecerán en
 *   ningún panel de coordinador (solo el admin los ve).
 *
 * - Al borrar un coordinador, los grupos quedan huérfanos (NULL) para no
 *   destruir histórico de asistencias, sesiones e instructorías.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('class_groups', 'coordinator_id')) {
            Schema::table('class_groups', function (Blueprint $table) {
                $table->foreignId('coordinator_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('coordinators')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('class_groups', 'coordinator_id')) {
            Schema::table('class_groups', function (Blueprint $table) {
                $table->dropConstrainedForeignId('coordinator_id');
            });
        }
    }
};
