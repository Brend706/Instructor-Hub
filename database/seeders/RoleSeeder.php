<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $now = now();

        DB::table('roles')->insert([
            ['name' => 'admin', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'coordinator', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'instructor', 'created_at' => $now, 'updated_at' => $now],
        ]);
    }
}
