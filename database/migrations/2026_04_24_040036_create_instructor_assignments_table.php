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
        Schema::create('instructor_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('instructor_id')->constrained('instructors');
            $table->foreignId('class_group_id')->constrained('class_groups');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('instructor_assignments');
    }
};
