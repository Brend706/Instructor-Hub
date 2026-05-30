<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class InstructorSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('instructors')->insert([
            [
                'user_id' => 3, // instructor demo
                'major' => 'Tecnico en Ingenieria de Software',
                'status' => 'Activo',
                'created_at' => now(),
                'updated_at' => now(),
                'coordinator_id' => 2
            ],
            [   //ID 2
                'user_id' => 7, // carlos.henriquez@mail.utec.edu.sv
                'major' => 'Ingeniería en Sistemas y Computación',
                'status' => 'Activo',
                'created_at' => now(),
                'updated_at' => now(),
                'coordinator_id' => 2
            ],
            [   //ID 3
                'user_id' => 8, // valeria.benitez@mail.utec.edu.sv
                'major' => 'Ingeniería en Sistemas y Computación',
                'status' => 'Activo',
                'created_at' => now(),
                'updated_at' => now(),
                'coordinator_id' => 2
            ],
            [   //ID 4
                'user_id' => 9, // victor.miranda@mail.utec.edu.sv
                'major' => 'Arquitectura',
                'status' => 'Activo',
                'created_at' => now(),
                'updated_at' => now(),
                'coordinator_id' => 3
            ],
        ]);
    }
}
