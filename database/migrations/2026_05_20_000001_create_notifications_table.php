<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabla estándar de notificaciones de Laravel.
 * Cada fila representa una notificación dirigida a un usuario (admin, coordinador, etc.).
 *
 * Columnas clave:
 *  - type: clase de notificación (p. ej. App\Notifications\InstructorCreated)
 *  - notifiable_type / notifiable_id: a quién va dirigida (User)
 *  - data (json): contenido del mensaje (instructor, coordinador, fecha)
 *  - read_at: NULL = no leída; timestamp = ya la abrió.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Si la tabla ya existe (por ejemplo si fue creada manualmente), no hace nada.
        if (Schema::hasTable('notifications')) {
            return;
        }

        Schema::create('notifications', function (Blueprint $table) {
            // PK: UUID (string), no autoincrement. Laravel genera el id al crear la notificación.
            $table->uuid('id')->primary();
            // Clase de notificación, ej. "App\\Notifications\\InstructorCreated".
            $table->string('type');
            // morphs() crea notifiable_type + notifiable_id (a quién va dirigida).
            // En este proyecto siempre es App\Models\User (admin).
            $table->morphs('notifiable');
            // JSON serializado con los datos del evento (instructor, creator, fecha).
            $table->text('data');
            // NULL = no leída; timestamp = momento en que el usuario la abrió.
            $table->timestamp('read_at')->nullable();
            // created_at / updated_at estándar.
            $table->timestamps();
        });
    }

    public function down(): void
    {
        // Rollback: simplemente borra la tabla.
        Schema::dropIfExists('notifications');
    }
};
