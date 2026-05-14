<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Usuario admin para pruebas: admin@test.com / 123456
 */
class AdminTestUserSeeder extends Seeder
{
    public function run(): void
    {
        $adminRole = Role::query()->firstOrCreate(['name' => 'admin']);

        User::query()->updateOrCreate(
            ['email' => 'admin@test.com'],
            [
                'name' => 'Admin',
                'password' => bcrypt('123456'),
                'role_id' => $adminRole->id,
            ]
        );
    }
}
