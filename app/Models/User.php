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
     * Nombre de ruta Laravel del panel principal según el rol (login, admin, coordinador, instructor).
     */
    public function dashboardRouteName(): string
    {
        return match ($this->role?->name) {
            'admin' => 'admin.dashboard',
            'coordinator' => 'coordinator.dashboard',
            'instructor' => 'instructor.dashboard',
            default => 'login',
        };
    }

    /**
     * Etiqueta del rol en español para la UI (perfil, sidebar admin, etc.).
     */
    public function roleDisplayLabel(): string
    {
        return match ($this->role?->name) {
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
