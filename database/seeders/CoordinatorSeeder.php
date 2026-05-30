<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CoordinatorSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('coordinators')->insert([
            [   //ID 1 de coordinador
                'user_id' => 2, // coordinator@test.com
                'school_name' => 'Escuela de Informatica',
                'catedra' => 'Sistemas Informaticos',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [   //ID 2
                'user_id' => 5, // alejandro.mendoza@mail.utec.edu.sv
                'school_name' => 'Escuela de Informatica',
                'catedra' => 'Programación',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [   //ID 3
                'user_id' => 6, // elena.rostova@mail.utec.edu.sv
                'school_name' => 'Escuela de Ciencias Aplicadas',
                'catedra' => 'Dibujo Arquitectonico y Construcción',
                'created_at' => now(),
                'updated_at' => now()
            ],
        ]);
    }
}
