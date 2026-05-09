<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Coordinator;
use App\Models\Instructor;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class InstructorController extends Controller
{
    private const INSTRUCTOR_ROLE_ID = 3;

    /** Carreras sugeridas si aún no hay datos en BD */
    private const DEFAULT_MAJORS = [
        'Ing. Sistemas',
        'Arquitectura',
        'Diseño Gráfico',
        'Ing. Civil',
        'Ing. Industrial',
    ];

    public function index(): View
    {
        $instructors = Instructor::query()
            ->with('user')
            ->latest()
            ->paginate(10);

        return view('admin.instructors.index', [
            'instructors' => $instructors,
            'carreras' => $this->carreraOptions(),
            'hasStatusColumn' => Schema::hasColumn('instructors', 'status'),
        ]);
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
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validatedInstructorRequest($request, null);

        DB::transaction(function () use ($validated) {
            /** @var User $user */
            $user = User::query()->create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => $validated['password'],
                'role_id' => self::INSTRUCTOR_ROLE_ID,
            ]);

            $data = [
                'user_id' => $user->id,
                'major' => $validated['major'],
            ];
            if (Schema::hasColumn('instructors', 'status')) {
                $data['status'] = $validated['status'];
            }

            Instructor::query()->create($data);
        });

        return redirect()
            ->route('admin.instructores.index')
            ->with('success', 'Instructor creado correctamente.');
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
            ->route('admin.instructores.index')
            ->with('success', 'Instructor actualizado correctamente.');
    }

    public function destroy(string $id): RedirectResponse
    {
        /** @var Instructor $instructor */
        $instructor = Instructor::query()->with('user')->findOrFail($id);

        DB::transaction(function () use ($instructor) {
            if ($instructor->user) {
                $instructor->user->delete();
            } else {
                $instructor->delete();
            }
        });

        return redirect()
            ->route('admin.instructores.index')
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

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($instructor?->user_id),
            ],
            'password' => $passwordRules,
            'major' => ['required', 'string', 'max:255'],
            'status' => $statusRules,
        ], [
            'name.required' => 'Debe ingresar el nombre completo.',
            'email.required' => 'Debe ingresar el correo electrónico.',
            'email.email' => 'El correo electrónico no es válido.',
            'email.unique' => 'Ese correo ya está registrado.',
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
