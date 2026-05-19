<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Una clase/sesión de instructoría (cuando el docente pulsa "Generar QR e iniciar").
 */
class ClassSession extends Model
{
    protected $fillable = [
        'instructor_assignment_id',
        'public_token',   // Va en la URL del QR: /asistencia/{public_token}
        'session_code',   // Texto bajo el QR, ej. PROGRAMA-2026-004
        'is_open',        // true = acepta asistencias; false = sesión finalizada
        'date',
        'start_time',
        'end_time',
        'comments',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'is_open' => 'boolean',
        ];
    }

    public function instructorAssignment(): BelongsTo
    {
        return $this->belongsTo(InstructorAssignment::class);
    }

    /**
     * Registros de asistencia de estudiantes en esta sesión (`student_attendances`).
     */
    public function attendances(): HasMany
    {
        return $this->hasMany(StudentAttendance::class, 'session_id');
    }

    /**
     * Enlace que se codifica dentro del QR y se muestra debajo del código.
     */
    public function attendanceUrl(): string
    {
        return route('attendance.show', $this->public_token);
    }
}
