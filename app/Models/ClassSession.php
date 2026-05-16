<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClassSession extends Model
{
    protected $fillable = [
        'instructor_assignment_id',
        'date',
        'start_time',
        'end_time',
        'classroom',
        'virtual_link',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    public function instructorAssignment(): BelongsTo
    {
        return $this->belongsTo(InstructorAssignment::class);
    }
}
