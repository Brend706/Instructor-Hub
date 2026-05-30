<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class InstructorAssigmentsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('instructor_assignments')->insert([
            [
                'instructor_id' => 2,
                'schedule' => 'Lunes 20:00-21:00',
                'class_group_id' => 1,
                'status' => 'Activo',
                'modality' => 'En linea',
                'classroom' => 'Teams',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'instructor_id' => 3,
                'schedule' => 'Jueves 20:30-21:30',
                'class_group_id' => 1,
                'status' => 'Activo',
                'modality' => 'En linea',
                'classroom' => 'Teams',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'instructor_id' => 4,
                'schedule' => 'Jueves 10:00-11:00',
                'class_group_id' => 1,
                'status' => 'Activo',
                'modality' => 'Presencial',
                'classroom' => 'Laboratorio 1, Edificio A',
                'created_at' => now(),
                'updated_at' => now()
            ]
        ]);
    }
}
