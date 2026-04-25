<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class InstructorSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('instructors')->insert([
            [
                'user_id' => 3, // instructor@test.com
                'major' => 'Programación',
                'created_at' => now(),
                'updated_at' => now()
            ]
        ]);
    }
}
