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
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
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
