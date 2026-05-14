<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    protected $fillable = [
        'name',
    ];

    /**
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * ID estable para `users.role_id` según el slug en `roles.name` (`admin`, `coordinator`, `instructor`).
     * Crea la fila si no existe, para no depender de IDs autonuméricos ni de seeders omitidos.
     */
    public static function idForSlug(string $slug): int
    {
        return (int) static::query()->firstOrCreate(['name' => $slug])->id;
    }
}
