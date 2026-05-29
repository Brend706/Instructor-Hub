<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('coordinators', function (Blueprint $table) {

            /*
             |--------------------------------------------------------------
             | coordination_name -> school_name
             |--------------------------------------------------------------
             */
            $table->renameColumn('coordination_name', 'school_name');

            /*
             |--------------------------------------------------------------
             | name -> catedra
             |--------------------------------------------------------------
             */
            $table->renameColumn('name', 'catedra');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('coordinators', function (Blueprint $table) {

            $table->renameColumn('school_name', 'coordination_name');

            $table->renameColumn('catedra', 'name');
        });
    }
};