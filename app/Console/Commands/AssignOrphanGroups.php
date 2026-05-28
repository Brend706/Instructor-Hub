<?php

namespace App\Console\Commands;

use App\Models\ClassGroup;
use App\Models\Coordinator;
use Illuminate\Console\Command;

/**
 * Asigna grupos huérfanos (`class_groups.coordinator_id` IS NULL) a un
 * coordinador específico. Útil únicamente como utilería one-off para los
 * grupos creados antes de la migración que agregó `coordinator_id`.
 *
 * Uso:
 *   php artisan groups:assign-orphans --coordinator=ID            (todos)
 *   php artisan groups:assign-orphans --coordinator=ID --group=ID (uno)
 *   php artisan groups:assign-orphans --list                      (solo listar)
 */
class AssignOrphanGroups extends Command
{
    protected $signature = 'groups:assign-orphans
        {--coordinator= : ID de la tabla coordinators que recibirá los grupos}
        {--group= : (opcional) ID de un único grupo a reasignar}
        {--list : Solo listar el estado actual sin modificar nada}';

    protected $description = 'Asigna grupos sin coordinador (huérfanos) a un coordinador existente';

    public function handle(): int
    {
        $orphans = ClassGroup::query()->whereNull('coordinator_id')->get();
        $coordinators = Coordinator::with('user')->get();

        $this->info("Coordinadores disponibles:");
        foreach ($coordinators as $c) {
            $label = $c->coordination_name ?? $c->name ?? 'Sin nombre';
            $this->line("  [{$c->id}] {$label} — {$c->user?->name}");
        }

        $this->newLine();
        $this->info("Grupos huérfanos: ".$orphans->count());
        foreach ($orphans as $g) {
            $this->line("  [{$g->id}] {$g->name} ({$g->professor})");
        }

        if ($this->option('list')) {
            return self::SUCCESS;
        }

        $coordId = $this->option('coordinator');
        if (! $coordId) {
            $this->error('Debes indicar --coordinator=ID');

            return self::INVALID;
        }

        if (! Coordinator::query()->whereKey($coordId)->exists()) {
            $this->error("No existe coordinador con id={$coordId}");

            return self::FAILURE;
        }

        $query = ClassGroup::query()->whereNull('coordinator_id');
        if ($this->option('group')) {
            $query->whereKey($this->option('group'));
        }

        $updated = $query->update(['coordinator_id' => $coordId]);

        $this->newLine();
        $this->info("Grupos reasignados: $updated");

        return self::SUCCESS;
    }
}
