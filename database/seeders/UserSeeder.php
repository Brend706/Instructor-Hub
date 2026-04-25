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
            'name' => 'Admin',
            'email' => 'admin@test.com',
            'password' => Hash::make('123456'),
            'role_id' => 1,
            'created_at' => now(),
            'updated_at' => now()
        ],
        [
            'name' => 'Coordinator Demo',
            'email' => 'coordinator@test.com',
            'password' => Hash::make('123456'),
            'role_id' => 2,
            'created_at' => now(),
            'updated_at' => now()
        ],
        [
            'name' => 'Instructor Demo',
            'email' => 'instructor@test.com',
            'password' => Hash::make('123456'),
            'role_id' => 3,
            'created_at' => now(),
            'updated_at' => now()
        ]
    ]);
    }
}
