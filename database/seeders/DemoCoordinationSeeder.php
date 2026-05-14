<?php

namespace Database\Seeders;

use App\Models\Coordinator;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Crea un usuario coordinador y el nombre de coordinación en `coordinators` (solo para pruebas locales).
 */
class DemoCoordinationSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $roleId = Role::idForSlug('coordinator');

            /** @var User $user */
            $user = User::query()->updateOrCreate(
                ['email' => 'coordinador@demo.com'],
                [
                    'name' => 'Coordinador Demo',
                    'password' => 'Coordinador123',
                    'role_id' => $roleId,
                ]
            );

            $data = [
                'user_id' => $user->id,
                'name' => 'Sistemas Informáticos',
            ];

            if (Schema::hasColumn('coordinators', 'coordination_name')) {
                $data['coordination_name'] = 'Sistemas Informáticos';
            }

            Coordinator::query()->updateOrCreate(
                ['user_id' => $user->id],
                $data
            );
        });
    }
}
