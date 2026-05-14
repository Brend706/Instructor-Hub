<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Columna `carnet` y limpieza de columnas heredadas (`codigo_materia`, `carne`) si existían.
     */
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            if (Schema::hasColumn('students', 'codigo_materia')) {
                $table->dropColumn('codigo_materia');
            }
        });

        if (Schema::hasColumn('students', 'carne') && ! Schema::hasColumn('students', 'carnet')) {
            Schema::table('students', function (Blueprint $table) {
                $table->renameColumn('carne', 'carnet');
            });
        } elseif (! Schema::hasColumn('students', 'carnet')) {
            Schema::table('students', function (Blueprint $table) {
                $table->string('carnet', 64)->nullable()->after('id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            if (Schema::hasColumn('students', 'carnet')) {
                $table->dropColumn('carnet');
            }
        });
    }
};
