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
        $row = [
            'user_id' => 3,
            'major' => 'Programación',
            'created_at' => now(),
            'updated_at' => now(),
        ];
        if (Schema::hasColumn('instructors', 'status')) {
            $row['status'] = 'Activo';
        }
        DB::table('instructors')->insert([$row]);
    }
}
