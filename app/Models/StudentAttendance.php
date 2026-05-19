<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Una fila = un estudiante marcó asistencia en una sesión (tras validar carnet en el grupo).
 * Tabla: student_attendances (session_id + student_id).
 */
class StudentAttendance extends Model
{
    protected $fillable = [
        'session_id',
        'student_id',
        'attended',
    ];

    protected function casts(): array
    {
        return [
            'attended' => 'boolean',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(ClassSession::class, 'session_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
