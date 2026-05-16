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
        //CAMBIOS EN LA TABLA INSTRUCTORS PARA AGREGAR EL CAMPO DE COORDINADOR Y CATEGORIA
        Schema::table('instructors', function (Blueprint $table) {

            //la categoria que pueden ser son BECADO PREGADO, HORAS SOCIALES, BECADO PREESPECIALIDAD, AD-HONOREM      
            $table->string('category') 
                  ->nullable();

            $table->foreignId('coordinator_id')
                ->nullable()
                ->constrained('coordinators')
                ->nullOnDelete();
        });

        //CAMBIO EN LA TABLA DE ASIGNACION DE INSTRUCTORES A UN GRUPO DE CLASE
        Schema::table('instructor_assignments', function (Blueprint $table) {

            $table->string('status') //Activo o Finalizado, por si ya paso el ciclo
                  ->default('Activo');
            $table->string('modality') //Presencial o Virtual, modalidad en que se dara la instructoria
                  ->nullable();
            $table->string('classroom')
                  ->nullable();
            $table->string('virtual_link')
                  ->nullable();
        });

        //CAMBIOS EN LA TABLA DE SESSION DE CADA INSTRUCTORIA
        Schema::table('class_sessions', function (Blueprint $table) {

            $table->string('comments') //se agrega esta columna para fines de retroalimentacion o comentarios que el instructor quiera agregar sobre la sesion
                  ->nullable();

            $table->dropColumn([ //se eliminan estas columnas porque ya no se necesitan, la modalidad y el aula se asignaran a nivel de instructoria y no de sesion
                'classroom',
                'virtual_link'
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //INTRUCTORES
        Schema::table('instructors', function (Blueprint $table) {

            $table->dropForeign(['coordinator_id']);

            $table->dropColumn([
                'coordinator_id',
                'category'
            ]);
        });

        //ASIGNACIONES DE INSTRUCTORES A GRUPO DE CLASE
        Schema::table('instructor_assignments', function (Blueprint $table) {

            $table->dropColumn([
                'status',
                'modality',
                'classroom',
                'virtual_link'
            ]);
        });

        //SESIONES DE CADA INSTRUCTORIA
        Schema::table('class_sessions', function (Blueprint $table) {

            $table->dropColumn('comments');

            $table->string('classroom')
                  ->nullable();

            $table->string('virtual_link')
                  ->nullable();
        });
    }
};
