<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Coordinator;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CoordinatorController extends Controller
{
    /**
     * Rol usado para usuarios coordinadores.
     *
     * Nota: se mantiene como constante para no depender de una consulta a la tabla
     * `roles` durante la creación.
     */
    private const COORDINATOR_ROLE_ID = 2;

    /**
     * Display a listing of the resource.
     */
    public function index(): View
    {
        $coordinators = Coordinator::query()
            ->with('user')
            ->latest()
            ->paginate(10);

        // Compatibilidad:
        // - En migración vieja `coordinators` tenía columna `name` (guardaba la coordinación).
        // - En la nueva migración agregamos `coordination_name`.
        // Mientras exista gente con la BD a medias (sin migrar), no podemos referenciar
        // `coordination_name` si aún no existe.
        $hasCoordinationName = Schema::hasColumn('coordinators', 'coordination_name');
        $coordinationExpr = $hasCoordinationName ? 'COALESCE(coordination_name, name)' : 'name';

        // Lista de coordinaciones para el filtro del select en la UI.
        $coordinaciones = Coordinator::query()
            ->selectRaw($coordinationExpr . ' as coordination')
            ->whereRaw($coordinationExpr . ' IS NOT NULL')
            ->distinct()
            ->orderBy('coordination')
            ->pluck('coordination')
            ->values()
            ->all();

        return view('admin.coordinators.index', [
            'coordinators' => $coordinators,
            'coordinaciones' => $coordinaciones,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'max:255'],
            'coordination_name' => ['required', 'string', 'max:255'],
        ]);

        /**
         * Transacción de 2 pasos:
         * 1) Crear registro en `users` con rol coordinator
         * 2) Crear registro en `coordinators` usando el user_id recién creado
         *
         * Si falla el paso 2, se revierte el paso 1 automáticamente.
         */
        DB::transaction(function () use ($validated) {
            /** @var User $user */
            $user = User::query()->create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => $validated['password'],
                'role_id' => self::COORDINATOR_ROLE_ID,
            ]);

            // Compatibilidad de columnas (BD migrada vs no migrada):
            // Siempre guardamos la coordinación en `name` (columna antigua).
            // Si la columna nueva `coordination_name` existe, también la llenamos.
            $data = [
                'user_id' => $user->id,
                'name' => $validated['coordination_name'],
            ];

            if (Schema::hasColumn('coordinators', 'coordination_name')) {
                $data['coordination_name'] = $validated['coordination_name'];
            }

            Coordinator::query()->create($data);
        });

        return redirect()
            ->route('admin.coordinadores.index')
            ->with('success', 'Coordinador creado correctamente.');
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id): RedirectResponse
    {
        /** @var Coordinator $coordinator */
        $coordinator = Coordinator::query()->with('user')->findOrFail($id);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($coordinator->user_id),
            ],
            'password' => ['nullable', 'string', 'min:8', 'max:255'],
            'coordination_name' => ['required', 'string', 'max:255'],
        ]);

        DB::transaction(function () use ($coordinator, $validated) {
            // El "coordinador" en realidad es un User + un registro en coordinators.
            // Por eso se actualizan ambos en una sola transacción.
            $coordinator->user->fill([
                'name' => $validated['name'],
                'email' => $validated['email'],
            ]);
            if (!empty($validated['password'])) {
                // Password es opcional al editar (si viene vacío, no se cambia).
                $coordinator->user->password = $validated['password'];
            }
            $coordinator->user->save();

            $data = [
                'name' => $validated['coordination_name'],
            ];
            if (Schema::hasColumn('coordinators', 'coordination_name')) {
                $data['coordination_name'] = $validated['coordination_name'];
            }

            $coordinator->fill($data);
            $coordinator->save();
        });

        return redirect()
            ->route('admin.coordinadores.index')
            ->with('success', 'Coordinador actualizado correctamente.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id): RedirectResponse
    {
        /** @var Coordinator $coordinator */
        $coordinator = Coordinator::query()->with('user')->findOrFail($id);

        DB::transaction(function () use ($coordinator) {
            // Eliminamos el user: por la FK `coordinators.user_id` con cascadeOnDelete,
            // el registro en coordinators se borra automáticamente.
            $coordinator->user->delete();
        });

        return redirect()
            ->route('admin.coordinadores.index')
            ->with('success', 'Coordinador eliminado correctamente.');
    }
}
