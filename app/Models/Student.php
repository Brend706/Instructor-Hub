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
        'carnet',
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

    /**
     * Ya existe en el mismo grupo otro estudiante con el mismo carnet o el mismo correo (sin distinguir mayúsculas en el correo).
     */
    public static function existsDuplicateInGroup(int $classGroupId, string $carnet, string $email): bool
    {
        $carnet = trim($carnet);
        $emailNorm = mb_strtolower(trim($email));

        return static::query()
            ->where('class_group_id', $classGroupId)
            ->where(function ($q) use ($carnet, $emailNorm) {
                $q->where('carnet', $carnet)
                    ->orWhereRaw('LOWER(TRIM(email)) = ?', [$emailNorm]);
            })
            ->exists();
    }
}
