<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Estudiante asociado a un grupo (`students.class_group_id` → `class_groups`).
 */
class Student extends Model
{
    protected $fillable = [
        'name',
        'email',
        'class_group_id',
    ];

    /**
     * @return BelongsTo<ClassGroup, $this>
     */
    public function classGroup(): BelongsTo
    {
        return $this->belongsTo(ClassGroup::class);
    }
}
