<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Instructor extends Model
{
    // ── Estados de cuenta ──────────────────────────────────────
    public const STATUS_ACTIVE    = 'Activo';
    public const STATUS_INACTIVE  = 'Inactivo';
    public const STATUS_SUSPENDED = 'Suspendido';
    public const STATUS_BLOCKED   = 'Bloqueado';

    /** Estados que impiden el inicio de sesión. */
    public const BLOCKED_STATUSES = [
        self::STATUS_SUSPENDED,
        self::STATUS_BLOCKED,
        self::STATUS_INACTIVE,
    ];

    protected $fillable = [
        'user_id',
        'major',
        'status',
        'coordinator_id',
        'category',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Coordinador propietario del instructor (puede ser NULL para datos
     * heredados de antes del aislamiento por coordinación).
     *
     * @return BelongsTo<Coordinator, $this>
     */
    public function coordinator(): BelongsTo
    {
        return $this->belongsTo(Coordinator::class);
    }

    /**
     * Grupos asignados a este instructor (vía `instructor_assignments`).
     *
     * @return HasMany<InstructorAssignment, $this>
     */
    public function instructorAssignments(): HasMany
    {
        return $this->hasMany(InstructorAssignment::class);
    }

    /**
     * Solicitudes de suspensión enviadas por este instructor.
     *
     * @return HasMany<SuspensionRequest, $this>
     */
    public function suspensionRequests(): HasMany
    {
        return $this->hasMany(SuspensionRequest::class);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function canLogin(): bool
    {
        return $this->isActive();
    }
}
