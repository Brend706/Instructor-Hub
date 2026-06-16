<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SuspensionRequest extends Model
{
    // ── Tipos de solicitud ─────────────────────────────────────
    public const TYPE_VOLUNTARY    = 'voluntary';
    public const TYPE_FORCE_MAJEURE = 'force_majeure';
    public const TYPE_OTHER        = 'other';

    public const TYPE_LABELS = [
        self::TYPE_VOLUNTARY     => 'Solicitud voluntaria',
        self::TYPE_FORCE_MAJEURE => 'Fuerza mayor',
        self::TYPE_OTHER         => 'Otra razón',
    ];

    // ── Estados de revisión ────────────────────────────────────
    public const STATUS_PENDING  = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    public const STATUS_LABELS = [
        self::STATUS_PENDING  => 'Pendiente',
        self::STATUS_APPROVED => 'Aprobada',
        self::STATUS_REJECTED => 'Rechazada',
    ];

    protected $fillable = [
        'instructor_id',
        'assignment_id',
        'type',
        'reason',
        'status',
        'reviewed_by',
        'admin_notes',
        'requested_at',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'requested_at' => 'datetime',
            'reviewed_at'  => 'datetime',
        ];
    }

    // ── Relaciones ─────────────────────────────────────────────

    /** @return BelongsTo<Instructor, $this> */
    public function instructor(): BelongsTo
    {
        return $this->belongsTo(Instructor::class);
    }

    /** @return BelongsTo<InstructorAssignment, $this> */
    public function assignment(): BelongsTo
    {
        return $this->belongsTo(InstructorAssignment::class);
    }

    /** @return BelongsTo<User, $this> */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    // ── Helpers ────────────────────────────────────────────────

    public function typeLabel(): string
    {
        return self::TYPE_LABELS[$this->type] ?? $this->type;
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }
}
