<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ClassGroupsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('class_groups')->insert([
            [
                'coordinator_id' => 2,
                'name' => 'Programación Orientada a Objetos', //nombre de la materia
                'professor' => 'Ing. Andrea Beatriz Solórzano',
                'semester' => '02-2024',
                'modality' => 'Presencial',
                'schedule' => 'Lunes y Miércoles 10:00-11:30',
                'classroom' => 'Aula 101',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'coordinator_id' => 2,
                'name' => 'Programación Orientada a Objetos', //nombre de la materia
                'professor' => 'Ing. Roberto Carlos Morales',
                'semester' => '02-2025',
                'modality' => 'Presencial',
                'schedule' => 'Lunes y Miércoles 15:00-17:30',
                'classroom' => 'Aula 301',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'coordinator_id' => 2,
                'name' => 'Desarrollo Web', //nombre de la materia
                'professor' => 'Ing. Silvia María Rodríguez',
                'semester' => '02-2025',
                'modality' => 'En linea',
                'schedule' => 'Martes y Jueves 08:30-10:00',
                'classroom' => 'Canal de Teams',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'coordinator_id' => 3,
                'name' => 'Dibujo Técnico y Geometría Descriptiva', //nombre de la materia
                'professor' => 'Arq. Fernando José Altamirano',
                'semester' => '02-2025',
                'modality' => 'Presencial',
                'schedule' => 'Miercoles y Sabado 08:30-10:00',
                'classroom' => 'Taller de Arquitectura 1, Edificio D',
                'created_at' => now(),
                'updated_at' => now()
            ]
        ]);
    }
}
