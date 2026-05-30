<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Coordinator extends Model
{
    protected $fillable = [
        'user_id',
        // Columnas nuevas tras las migraciones más recientes.
        'school_name',
        'catedra',
        // Compatibilidad con versiones anteriores.
        'coordination_name',
        'name',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

