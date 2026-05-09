<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coordinators', function (Blueprint $table) {
            // Migración "safe": si alguien ya tiene la columna (por haber migrado),
            // no intentamos crearla de nuevo.
            if (!Schema::hasColumn('coordinators', 'coordination_name')) {
                $table->string('coordination_name')->nullable()->after('user_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('coordinators', function (Blueprint $table) {
            // Reversión "safe": solo se elimina si existe.
            if (Schema::hasColumn('coordinators', 'coordination_name')) {
                $table->dropColumn('coordination_name');
            }
        });
    }
};

