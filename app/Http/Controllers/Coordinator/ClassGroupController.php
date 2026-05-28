<?php

namespace App\Http\Controllers\Coordinator;

use App\Http\Controllers\Controller;
use App\Models\ClassGroup;
use App\Models\Coordinator;
use App\Models\Instructor;
use App\Models\InstructorAssignment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * CRUD de grupos de clase para el coordinador (tabla `class_groups` + conteos y asignación en `instructor_assignments`).
 */
class ClassGroupController extends Controller
{
    /**
     * Lista grupos con filtros opcionales (coinciden con la barra de búsqueda / selects de la vista).
     */
    public function index(Request $request): View
    {
        $coordinatorId = $this->currentCoordinatorId();

        // Aislamiento por coordinador: cada uno ve SOLO los grupos que él creó.
        // Los grupos huérfanos (sin coordinator_id) solo los gestiona el admin.
        $query = ClassGroup::query()
            ->with(['instructorAssignments' => fn ($q) => $q->with('instructor.user')])
            ->withCount('students')
            ->where('coordinator_id', $coordinatorId ?? -1);

        // Filtro texto: materia (`name`) o docente de la materia (`professor`)
        if ($request->filled('search')) {
            $term = '%'.$request->input('search').'%';
            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', $term)
                    ->orWhere('professor', 'like', $term);
            });
        }

        if ($request->filled('cycle')) {
            $query->where('semester', $request->input('cycle'));
        }

        if ($request->filled('modality')) {
            $query->where('modality', $request->input('modality'));
        }

        $groups = $query->orderBy('name')->get()->map(fn (ClassGroup $g) => $this->groupRow($g));

        // Ciclos distintos para el desplegable (columna `semester`)
        $cycles = ClassGroup::query()
            ->whereNotNull('semester')
            ->distinct()
            ->orderBy('semester')
            ->pluck('semester');

        // Instructores para el modal: SOLO los del coordinador autenticado.
        // Así no puede asignar un tutor que pertenece a otra coordinación.
        $instructors = Instructor::query()
            ->with('user')
            ->where('coordinator_id', $coordinatorId ?? -1)
            ->orderBy('id')
            ->get();

        return view('coordinator.groups.index', [
            'groups' => $groups,
            'cycles' => $cycles,
            'instructors' => $instructors,
            'filters' => [
                'search' => $request->input('search', ''),
                'cycle' => $request->input('cycle', ''),
                'modality' => $request->input('modality', ''),
            ],
        ]);
    }

    /**
     * Alta: guarda en `class_groups`. Aula física o enlace virtual según modalidad (un solo campo `classroom` en BD).
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateGroupPayload($request);
        $classroomValue = $this->resolvedClassroomField($validated);

        // Cada grupo nace asociado al coordinador que lo crea (no más huérfanos).
        ClassGroup::query()->create([
            'name' => $validated['subject'],
            'professor' => $validated['teacher'],
            'semester' => $validated['cycle'],
            'modality' => $validated['modality'],
            'schedule' => $validated['schedule'],
            'classroom' => $classroomValue,
            'coordinator_id' => $this->currentCoordinatorId(),
        ]);

        return redirect()
            ->route('coordinator.groups.index')
            ->with('success', 'Grupo creado correctamente.');
    }

    /**
     * Actualización de un grupo existente.
     */
    public function update(Request $request, ClassGroup $group): RedirectResponse
    {
        $this->ensureOwnsGroup($group);

        $validated = $this->validateGroupPayload($request);
        $classroomValue = $this->resolvedClassroomField($validated);

        $group->update([
            'name' => $validated['subject'],
            'professor' => $validated['teacher'],
            'semester' => $validated['cycle'],
            'modality' => $validated['modality'],
            'schedule' => $validated['schedule'],
            'classroom' => $classroomValue,
        ]);

        return redirect()
            ->route('coordinator.groups.index')
            ->with('success', 'Grupo actualizado correctamente.');
    }

    /**
     * Baja: elimina asignaciones de instructores y el grupo (estudiantes en cascada según migración `students`).
     */
    public function destroy(ClassGroup $group): RedirectResponse
    {
        $this->ensureOwnsGroup($group);

        DB::transaction(function () use ($group) {
            $group->instructorAssignments()->delete();
            $group->delete();
        });

        return redirect()
            ->route('coordinator.groups.index')
            ->with('success', 'Grupo eliminado correctamente.');
    }

    /**
     * Asigna un instructor tutor al grupo (reemplaza asignaciones previas para mantener una fila visible en la tabla).
     *
     * Reglas de aislamiento entre coordinaciones:
     *  1. El grupo debe pertenecer al coordinador autenticado.
     *  2. El instructor que se pretende asignar también debe ser suyo
     *     (coordinator_id coincide con el del coordinador logueado).
     *
     * Si cualquiera de las dos condiciones no se cumple → 404 silencioso
     * para no filtrar la existencia del recurso ajeno.
     */
    public function assignInstructor(Request $request, ClassGroup $group): RedirectResponse
    {
        $this->ensureOwnsGroup($group);

        $coordinatorId = $this->currentCoordinatorId();

        $validated = $request->validate([
            // exists con cláusula `where` para forzar que el instructor sea
            // de la misma coordinación; Laravel rechaza el form si no lo es.
            'instructor_id' => [
                'required',
                'integer',
                Rule::exists('instructors', 'id')->where(function ($q) use ($coordinatorId) {
                    $q->where('coordinator_id', $coordinatorId ?? -1);
                }),
            ],
        ], [
            'instructor_id.exists' => 'El instructor seleccionado no pertenece a tu coordinación.',
        ]);

        // Regla: un instructor solo puede tener UNA instructoría ACTIVA por
        // ciclo (`class_groups.semester`). Si ya tiene otra activa en el
        // mismo ciclo, rechazamos antes de tocar la BD.
        if ($conflictGroup = $this->findActiveAssignmentInSameSemester($group, (int) $validated['instructor_id'])) {
            return back()
                ->withErrors([
                    'instructor_id' => 'Este instructor ya tiene una instructoría activa en el ciclo '.
                        ($group->semester ?? '—').' (Grupo: '.$conflictGroup->name.'). '.
                        'Finalizá esa primero o asigná otro instructor.',
                ])
                ->withInput();
        }

        DB::transaction(function () use ($group, $validated) {
            $group->instructorAssignments()->delete();
            $group->instructorAssignments()->create([
                'instructor_id' => $validated['instructor_id'],
            ]);
        });

        return redirect()
            ->route('coordinator.groups.index')
            ->with('success', 'Instructor asignado al grupo.');
    }

    /**
     * Busca si el instructor ya tiene una instructoría activa en otro grupo
     * del mismo ciclo (`semester`). Devuelve el ClassGroup en conflicto o
     * null si no hay choque.
     *
     * Reglas:
     *  - "Activa" = `instructor_assignments.status` IS NULL o = 'Activo'.
     *    Si la columna `status` no existe (BD legacy), TODAS las
     *    asignaciones cuentan como activas.
     *  - Se ignora el grupo destino (`$targetGroup->id`) porque podría
     *    estarse reasignando el mismo grupo.
     *  - Si el grupo destino no tiene `semester`, no aplicamos la regla
     *    (no hay forma de validar contra qué ciclo).
     */
    private function findActiveAssignmentInSameSemester(ClassGroup $targetGroup, int $instructorId): ?ClassGroup
    {
        if (empty($targetGroup->semester)) {
            return null;
        }

        $hasStatus = Schema::hasColumn('instructor_assignments', 'status');

        $conflict = InstructorAssignment::query()
            ->where('instructor_id', $instructorId)
            ->whereHas('classGroup', function ($q) use ($targetGroup) {
                $q->where('semester', $targetGroup->semester)
                    ->where('id', '!=', $targetGroup->id);
            })
            ->when($hasStatus, function ($q) {
                $q->where(function ($qq) {
                    $qq->whereNull('status')->orWhere('status', 'Activo');
                });
            })
            ->with('classGroup:id,name,semester')
            ->first();

        return $conflict?->classGroup;
    }

    /**
     * Devuelve el id (en `coordinators`) del usuario logueado, o NULL si no
     * existe. Centralizado en un helper privado para no repetir la consulta.
     */
    private function currentCoordinatorId(): ?int
    {
        return Coordinator::query()
            ->where('user_id', auth()->id())
            ->value('id');
    }

    /**
     * Garantiza que el grupo pertenezca al coordinador autenticado.
     * Si no, aborta con 404 (mejor que 403 para no filtrar existencia).
     */
    private function ensureOwnsGroup(ClassGroup $group): void
    {
        $coordinatorId = $this->currentCoordinatorId();
        if ($coordinatorId === null || (int) $group->coordinator_id !== (int) $coordinatorId) {
            abort(404);
        }
    }

    /**
     * Convierte el modelo en el arreglo que consume la vista Blade y el script de filtros en cliente.
     *
     * @return array{id:int, subject:string, teacher:string, cycle:string, schedule:string, modality:string, classroom:string, instructor:?string, students:int}
     */
    private function groupRow(ClassGroup $group): array
    {
        $assignment = $group->instructorAssignments->first();
        $instructorName = $assignment?->instructor?->user?->name;

        return [
            'id' => $group->id,
            'subject' => $group->name,
            'teacher' => $group->professor,
            'cycle' => $group->semester,
            'schedule' => $group->schedule,
            'modality' => $group->modality,
            'classroom' => $group->classroom,
            'instructor' => $instructorName,
            'students' => (int) $group->students_count,
        ];
    }

    /**
     * Validación compartida crear/editar (nombres de campos del formulario modal).
     *
     * @return array{subject:string, teacher:string, cycle:string, modality:string, schedule:string, classroom:?string, link:?string}
     */
    private function validateGroupPayload(Request $request): array
    {
        return $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'teacher' => ['required', 'string', 'max:255'],
            'cycle' => ['required', 'string', 'max:255'],
            'modality' => ['required', Rule::in(['Presencial', 'En línea'])],
            'schedule' => ['required', 'string', 'max:255'],
            'classroom' => [Rule::requiredIf($request->input('modality') === 'Presencial'), 'nullable', 'string', 'max:255'],
            'link' => [Rule::requiredIf($request->input('modality') === 'En línea'), 'nullable', 'string', 'max:2048'],
        ], [
            'subject.required' => 'Indica la materia.',
            'teacher.required' => 'Indica el docente de la materia.',
            'cycle.required' => 'Indica el ciclo.',
            'schedule.required' => 'Indica el horario.',
            'classroom.required' => 'Indica el aula o ubicación física.',
            'link.required' => 'Indica el enlace de la sesión virtual.',
        ]);
    }

    /**
     * Unifica aula presencial y enlace en la columna `classroom` de la base de datos.
     */
    private function resolvedClassroomField(array $validated): string
    {
        if ($validated['modality'] === 'Presencial') {
            return $validated['classroom'] ?? '';
        }

        return $validated['link'] ?? '';
    }
}
