<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Garantiza la columna `professor` en `class_groups`.
 *
 * En algunos entornos la migración que renombra `career` → `professor` no se ejecutó;
 * MySQL entonces falla al insertar porque el modelo usa `professor`.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('class_groups')) {
            return;
        }

        if (Schema::hasColumn('class_groups', 'professor')) {
            return;
        }

        if (Schema::hasColumn('class_groups', 'career')) {
            Schema::table('class_groups', function (Blueprint $table) {
                $table->renameColumn('career', 'professor');
            });

            return;
        }

        Schema::table('class_groups', function (Blueprint $table) {
            $table->string('professor')->after('name');
        });
    }

    public function down(): void
    {
        // Reversión omitida: `up` puede haber renombrado o creado columna; deshacer a mano en SQL si hace falta.
    }
};
