<?php

namespace App\Http\Controllers\Coordinator;

use App\Http\Controllers\Controller;
use App\Models\ClassGroup;
use App\Models\Instructor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
        $query = ClassGroup::query()
            ->with(['instructorAssignments' => fn ($q) => $q->with('instructor.user')])
            ->withCount('students');

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

        // Instructores para el modal (tabla `instructors` + `users` para el nombre)
        $instructors = Instructor::query()
            ->with('user')
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

        ClassGroup::query()->create([
            'name' => $validated['subject'],
            'professor' => $validated['teacher'],
            'semester' => $validated['cycle'],
            'modality' => $validated['modality'],
            'schedule' => $validated['schedule'],
            'classroom' => $classroomValue,
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
     */
    public function assignInstructor(Request $request, ClassGroup $group): RedirectResponse
    {
        $validated = $request->validate([
            'instructor_id' => ['required', 'integer', 'exists:instructors,id'],
        ]);

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
