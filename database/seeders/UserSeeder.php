<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('users')->insert([
        [
            'name' => 'Admin Demo',
            'email' => 'admin@test.com',
            'password' => Hash::make('12345678'),
            'role_id' => 1,
            'created_at' => now(),
            'updated_at' => now()
        ],
        [
            'name' => 'Coordinator Demo',
            'email' => 'coordinator@test.com',
            'password' => Hash::make('12345678'),
            'role_id' => 2,
            'created_at' => now(),
            'updated_at' => now()
        ],
        [
            'name' => 'Instructor Demo',
            'email' => 'instructor@test.com',
            'password' => Hash::make('12345678'),
            'role_id' => 3,
            'created_at' => now(),
            'updated_at' => now()
        ],
        [   //ID 4
            'name' => 'Admin UTEC',
            'email' => 'admin_fica@mail.utec.edu.sv',
            'password' => Hash::make('12345678'),
            'role_id' => 1,
            'created_at' => now(),
            'updated_at' => now()
        ],
        [   //ID 5 para crear un coordinador
            'name' => 'Alejandro Mendoza Castro',
            'email' => 'alejandro.mendoza@mail.utec.edu.sv',
            'password' => Hash::make('12345678'),
            'role_id' => 2,
            'created_at' => now(),
            'updated_at' => now()
        ],
        [   //ID 6 para crear otro coordinador
            'name' => 'Elena Rostova Fuentes',
            'email' => 'elena.rostova@mail.utec.edu.sv',
            'password' => Hash::make('12345678'),
            'role_id' => 2,
            'created_at' => now(),
            'updated_at' => now()
        ],
        [   //ID 7 para crear un instructor
            'name' => 'Carlos Eduardo Henríquez Palacios',
            'email' => 'carlos.henriquez@mail.utec.edu.sv',
            'password' => Hash::make('12345678'),
            'role_id' => 3,
            'created_at' => now(),
            'updated_at' => now()
        ],
        [   //ID 8 para crear otra instructor
            'name' => 'Valeria Sofía Benítez Miranda',
            'email' => 'valeria.benitez@mail.utec.edu.sv',
            'password' => Hash::make('12345678'),
            'role_id' => 3,
            'created_at' => now(),
            'updated_at' => now()
        ],
        [   //ID 9 para crear otra instructor
            'name' => 'Victor Josue Miranda Rodriguez',
            'email' => 'victor.miranda@mail.utec.edu.sv',
            'password' => Hash::make('12345678'),
            'role_id' => 3,
            'created_at' => now(),
            'updated_at' => now()
        ],
    ]);
    }
}
