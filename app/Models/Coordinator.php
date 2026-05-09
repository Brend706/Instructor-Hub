<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Coordinator extends Model
{
    protected $fillable = [
        'user_id',
        // Columna nueva (cuando la migración 2026_05_08... ya fue ejecutada)
        'coordination_name',
        // Compatibilidad: migración antigua usaba `name` para guardar la coordinación
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

