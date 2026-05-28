<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Instructor extends Model
{
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
}
