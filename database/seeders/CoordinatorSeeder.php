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
            [
                'user_id' => 2, // coordinator@test.com
                'name' => 'Sistemas Informaticos',
                'created_at' => now(),
                'updated_at' => now()
            ]
        ]);
    }
}
