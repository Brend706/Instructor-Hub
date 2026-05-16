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
        Schema::table('students', function (Blueprint $table) {

            // Código de materia / asignatura
            $table->string('subject_code', 20)
                ->nullable()
                ->after('name');

            // Carnet del estudiante
            $table->string('carnet', 50)
                ->nullable()
                ->after('id');

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {

            $table->dropColumn('subject_code');
            $table->dropColumn('carnet');

        });
    }
};