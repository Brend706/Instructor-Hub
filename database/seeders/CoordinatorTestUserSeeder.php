<?php

namespace Database\Seeders;

use App\Models\Coordinator;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Coordinador para pruebas: coordinator@test.com / 123456
 */
class CoordinatorTestUserSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $roleId = Role::idForSlug('coordinator');

            /** @var User $user */
            $user = User::query()->updateOrCreate(
                ['email' => 'coordinator@test.com'],
                [
                    'name' => 'Coordinator Demo',
                    'password' => bcrypt('123456'),
                    'role_id' => $roleId,
                ]
            );

            $coordination = 'Coordinación Demo';

            $data = [
                'user_id' => $user->id,
                'name' => $coordination,
            ];

            if (Schema::hasColumn('coordinators', 'coordination_name')) {
                $data['coordination_name'] = $coordination;
            }

            Coordinator::query()->updateOrCreate(
                ['user_id' => $user->id],
                $data
            );
        });
    }
}
