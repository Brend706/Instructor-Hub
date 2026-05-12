<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
        ];
    }

    /**
     * @return BelongsTo<Role, $this>
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * Slug estable del rol (`admin`, `coordinator`, `instructor`) para autorización y redirecciones.
     *
     * 1) Si existe la relación `role` y `roles.name` es uno de los slugs anteriores, se usa ese valor
     *    (convención Laravel / seeders).
     * 2) Si no (p. ej. datos creados en phpMyAdmin con otro texto en `name`), se infiere desde `role_id`:
     *    1 = admin, 2 = coordinador, 3 = instructor — alineado con los seeders por defecto.
     */
    public function roleSlug(): ?string
    {
        $this->loadMissing('role');
        $name = $this->role?->name;
        if (in_array($name, ['admin', 'coordinator', 'instructor'], true)) {
            return $name;
        }

        // Respaldo por FK: mismo criterio que `RoleSeeder` / usuarios con `role_id` correcto.
        return match ((int) ($this->role_id ?? 0)) {
            1 => 'admin',
            2 => 'coordinator',
            3 => 'instructor',
            default => null,
        };
    }

    /**
     * Nombre de ruta del panel tras login o `/dashboard` (p. ej. `coordinator.dashboard`).
     * Delega en `roleSlug()` para que coincida con el middleware `role:*`.
     */
    public function dashboardRouteName(): string
    {
        return match ($this->roleSlug()) {
            'admin' => 'admin.dashboard',
            'coordinator' => 'coordinator.dashboard',
            'instructor' => 'instructor.dashboard',
            default => 'login',
        };
    }

    /**
     * Texto del rol en español para vistas (perfil, etc.); usa el mismo `roleSlug()` que el resto de la app.
     */
    public function roleDisplayLabel(): string
    {
        return match ($this->roleSlug()) {
            'admin' => 'Administrador',
            'coordinator' => 'Coordinador',
            'instructor' => 'Instructor',
            default => 'Usuario',
        };
    }

    /**
     * Iniciales para avatar (primera letra del primer y último nombre, o dos letras del nombre).
     */
    public function initials(): string
    {
        $name = trim($this->name);
        if ($name === '') {
            return '??';
        }

        $parts = preg_split('/\s+/u', $name, -1, PREG_SPLIT_NO_EMPTY);
        if (count($parts) >= 2) {
            $first = mb_substr($parts[0], 0, 1);
            $last = mb_substr($parts[count($parts) - 1], 0, 1);

            return mb_strtoupper($first.$last);
        }

        return mb_strtoupper(mb_substr($name, 0, min(2, mb_strlen($name))));
    }
}
