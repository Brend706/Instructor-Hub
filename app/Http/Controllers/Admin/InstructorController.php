<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Coordinator;
use App\Models\Instructor;
use App\Models\Role;
use App\Models\User;
use App\Notifications\InstructorCreated;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
// Facade `Notification`: envía la notificación a múltiples destinatarios (los admins).
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class InstructorController extends Controller
{
    /** Carreras sugeridas si aún no hay datos en BD */
    private const DEFAULT_MAJORS = [
        'Ing. Sistemas',
        'Arquitectura',
        'Diseño Gráfico',
        'Ing. Civil',
        'Ing. Industrial',
    ];

    /** Coordinaciones a ocultar en los selects (datos de prueba, etc.). */
    private const HIDDEN_COORDINATIONS = [
        'Coordinación Demo',
    ];

    /**
     * Get the correct route name prefix based on the authenticated user's role.
     */
    private function getRoutePrefix(): string
    {
        return auth()->user()->roleSlug().'.instructores';
    }

    public function index(): View
    {
        $query = Instructor::query()
            ->with('user')
            ->latest();

        $viewName = 'instructors.index';
        if (auth()->user()->roleSlug() === 'coordinator') {
            $query->where('coordinator_id', $this->currentCoordinatorId());
            $viewName = 'coordinator.instructors.index';
        }

        $instructors = $query->paginate(10);

        return view($viewName, [
            'instructors' => $instructors,
            'carreras' => $this->carreraOptions(),
            'hasStatusColumn' => Schema::hasColumn('instructors', 'status'),
        ]);
    }

    private function currentCoordinatorId(): ?int
    {
        if (auth()->user()->roleSlug() !== 'coordinator') {
            return null;
        }

        return Coordinator::query()
            ->where('user_id', auth()->id())
            ->value('id');
    }

    /**
     * Valores para filtro y selects: carreras existentes + coordinaciones registradas.
     *
     * @return list<string>
     */
    private function carreraOptions(): array
    {
        $fromInstructors = Instructor::query()
            ->whereNotNull('major')
            ->where('major', '!=', '')
            ->distinct()
            ->orderBy('major')
            ->pluck('major')
            ->all();

        $fromCoordinators = [];
        if (Schema::hasTable('coordinators')) {
            $hasCoordinationName = Schema::hasColumn('coordinators', 'coordination_name');
            $coordinationExpr = $hasCoordinationName ? 'COALESCE(coordination_name, name)' : 'name';
            $fromCoordinators = Coordinator::query()
                ->selectRaw($coordinationExpr.' as coordination')
                ->whereRaw($coordinationExpr.' IS NOT NULL')
                ->whereNotIn(DB::raw($coordinationExpr), self::HIDDEN_COORDINATIONS)
                ->distinct()
                ->orderBy('coordination')
                ->pluck('coordination')
                ->values()
                ->all();
        }

        return collect($fromInstructors)
            ->merge($fromCoordinators)
            ->merge(self::DEFAULT_MAJORS)
            ->filter()
            // Quitamos también coordinaciones ocultas si aparecieran en carreras de instructores.
            ->reject(fn ($value) => in_array($value, self::HIDDEN_COORDINATIONS, true))
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validatedInstructorRequest($request, null);

        $instructor = DB::transaction(function () use ($validated): Instructor {
            /** @var User $user */
            $user = User::query()->create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => $validated['password'],
                'role_id' => Role::idForSlug('instructor'),
            ]);

            $data = [
                'user_id' => $user->id,
                'major' => $validated['major'],
            ];
            if (auth()->user()->roleSlug() === 'coordinator') {
                $data['coordinator_id'] = $this->currentCoordinatorId();
            }
            if (Schema::hasColumn('instructors', 'status')) {
                $data['status'] = $validated['status'];
            }

            return Instructor::query()->create($data);
        });

        // Tras crear, intenta avisar a los administradores (solo si lo creó un coordinador).
        // fresh('user') recarga la relación user para que la notificación tenga nombre/correo actualizados.
        $this->notifyAdminsIfCreatedByCoordinator($instructor->fresh('user'), $request->user());

        return redirect()
            ->route($this->getRoutePrefix().'.index')
            ->with('success', 'Instructor creado correctamente.');
    }

    /**
     * Si quien crea el instructor es un coordinador, avisa a TODOS los administradores
     * vía la tabla `notifications` (se ve en la campanita del layout admin).
     *
     * - Solo se dispara cuando el creador tiene rol `coordinator`.
     * - El admin que esté logueado nunca se autonotifica.
     */
    private function notifyAdminsIfCreatedByCoordinator(Instructor $instructor, ?User $creator): void
    {
        // Filtro inicial: si no hay sesión o el creador NO es coordinador, no se notifica nada.
        if (! $creator || $creator->roleSlug() !== 'coordinator') {
            return;
        }

        // Buscamos a todos los usuarios cuyo rol (`roles.name`) sea exactamente 'admin'.
        // Excluimos al creador por si en algún caso límite también fuera admin (no auto-aviso).
        $admins = User::query()
            ->whereHas('role', fn ($q) => $q->where('name', 'admin'))
            ->where('id', '!=', $creator->id)
            ->get();

        // Si no hay administradores en el sistema, no hay a quién enviar.
        if ($admins->isEmpty()) {
            return;
        }

        // Notification::send inserta una fila en `notifications` por cada admin destinatario.
        // El contenido (data JSON) lo arma InstructorCreated::toArray().
        Notification::send($admins, new InstructorCreated($instructor, $creator));
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        /** @var Instructor $instructor */
        $instructor = Instructor::query()->with('user')->findOrFail($id);

        $validated = $this->validatedInstructorRequest($request, $instructor);

        DB::transaction(function () use ($instructor, $validated) {
            $instructor->user->fill([
                'name' => $validated['name'],
                'email' => $validated['email'],
            ]);
            if (! empty($validated['password'])) {
                $instructor->user->password = $validated['password'];
            }
            $instructor->user->save();

            $data = [
                'major' => $validated['major'],
            ];
            if (Schema::hasColumn('instructors', 'status')) {
                $data['status'] = $validated['status'];
            }
            $instructor->fill($data);
            $instructor->save();
        });

        return redirect()
            ->route($this->getRoutePrefix().'.index')
            ->with('success', 'Instructor actualizado correctamente.');
    }

    public function destroy(string $id): RedirectResponse
    {
        /** @var Instructor $instructor */
        $instructor = Instructor::query()->with('user')->findOrFail($id);

        // Si el instructor tiene tutorías (filas en `instructor_assignments`),
        // NO podemos borrarlo porque rompe la FK. Mostramos un mensaje amistoso.
        $assignmentsCount = $instructor->instructorAssignments()->count();
        if ($assignmentsCount > 0) {
            $name = $instructor->user?->name ?? 'el instructor';

            return redirect()
                ->route($this->getRoutePrefix().'.index')
                ->with('error', "No se puede eliminar a {$name} porque tiene {$assignmentsCount} tutoría(s) asignada(s). Quita primero sus asignaciones de grupo antes de borrarlo.");
        }

        try {
            DB::transaction(function () use ($instructor) {
                if ($instructor->user) {
                    $instructor->user->delete();
                } else {
                    $instructor->delete();
                }
            });
        } catch (\Illuminate\Database\QueryException $e) {
            // Red de seguridad por si hay otra FK pendiente (sesiones, etc.).
            // Código 23000 = integrity constraint violation en MySQL.
            if ($e->getCode() === '23000') {
                return redirect()
                    ->route($this->getRoutePrefix().'.index')
                    ->with('error', 'No se puede eliminar este instructor porque tiene registros relacionados en el sistema.');
            }

            throw $e;
        }

        return redirect()
            ->route($this->getRoutePrefix().'.index')
            ->with('success', 'Instructor eliminado correctamente.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedInstructorRequest(Request $request, ?Instructor $instructor): array
    {
        $passwordRules = $instructor === null
            ? ['required', 'string', 'min:8', 'max:255']
            : ['nullable', 'string', 'min:8', 'max:255'];

        $statusRules = Schema::hasColumn('instructors', 'status')
            ? ['required', 'string', Rule::in(['Activo', 'Inactivo'])]
            : ['nullable'];

        $emailUnique = Rule::unique('users', 'email');
        if ($instructor !== null && $instructor->user_id) {
            $emailUnique->ignore($instructor->user_id);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
                $emailUnique,
            ],
            'password' => $passwordRules,
            'major' => ['required', 'string', 'max:255'],
            'status' => $statusRules,
        ], [
            'name.required' => 'Debe ingresar el nombre completo.',
            'email.required' => 'Debe ingresar el correo electrónico.',
            'email.email' => 'El correo electrónico no es válido.',
            'email.unique' => 'Ese correo ya está registrado en el sistema.',
            'password.required' => 'Debe ingresar una contraseña.',
            'password.min' => 'La contraseña debe tener al menos 8 caracteres.',
            'major.required' => 'Debe seleccionar la carrera.',
            'status.required' => 'Debe seleccionar el estado.',
            'status.in' => 'El estado debe ser Activo o Inactivo.',
        ]);

        if (! Schema::hasColumn('instructors', 'status')) {
            unset($validated['status']);
        }

        return $validated;
    }
}
