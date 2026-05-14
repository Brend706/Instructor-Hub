<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Coordinator;
use App\Models\Role;
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
     * Display a listing of the resource.
     */
    public function index(): View
    {
        $coordinators = Coordinator::query()
            ->with('user')
            ->latest()
            ->paginate(10);

        // `coordination_name` (nuevo) vs `name` (legado): expresión compatible si falta la columna.
        $hasCoordinationName = Schema::hasColumn('coordinators', 'coordination_name');
        $coordinationExpr = $hasCoordinationName ? 'COALESCE(coordination_name, name)' : 'name';

        // Lista de coordinaciones para el filtro del select en la UI.
        $coordinaciones = Coordinator::query()
            ->selectRaw($coordinationExpr.' as coordination')
            ->whereRaw($coordinationExpr.' IS NOT NULL')
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
     * Store a newly created resource in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'max:255'],
            'coordination_name' => ['required', 'string', 'max:255'],
        ], [
            'name.required' => 'Debe ingresar el nombre completo.',
            'email.required' => 'Debe ingresar el correo electrónico.',
            'email.email' => 'El correo electrónico no es válido.',
            'email.unique' => 'Ese correo ya está registrado en el sistema.',
            'password.required' => 'Debe ingresar una contraseña.',
            'password.min' => 'La contraseña debe tener al menos 8 caracteres.',
            'coordination_name.required' => 'Debe seleccionar o indicar la coordinación.',
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
                'role_id' => Role::idForSlug('coordinator'),
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
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id): RedirectResponse
    {
        /** @var Coordinator $coordinator */
        $coordinator = Coordinator::query()->with('user')->findOrFail($id);

        $emailUnique = Rule::unique('users', 'email')->ignore($coordinator->user_id);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
                $emailUnique,
            ],
            'password' => ['nullable', 'string', 'min:8', 'max:255'],
            'coordination_name' => ['required', 'string', 'max:255'],
        ], [
            'name.required' => 'Debe ingresar el nombre completo.',
            'email.required' => 'Debe ingresar el correo electrónico.',
            'email.email' => 'El correo electrónico no es válido.',
            'email.unique' => 'Ese correo ya está registrado en el sistema.',
            'password.min' => 'La contraseña debe tener al menos 8 caracteres.',
            'coordination_name.required' => 'Debe seleccionar o indicar la coordinación.',
        ]);

        DB::transaction(function () use ($coordinator, $validated) {
            // El "coordinador" en realidad es un User + un registro en coordinators.
            // Por eso se actualizan ambos en una sola transacción.
            $coordinator->user->fill([
                'name' => $validated['name'],
                'email' => $validated['email'],
            ]);
            if (! empty($validated['password'])) {
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
            if ($coordinator->user) {
                // Borrar el usuario; la FK en `coordinators` suele eliminar el registro del coordinador en cascada.
                $coordinator->user->delete();
            } else {
                $coordinator->delete();
            }
        });

        return redirect()
            ->route('admin.coordinadores.index')
            ->with('success', 'Coordinador eliminado correctamente.');
    }
}
