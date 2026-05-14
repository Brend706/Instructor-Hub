<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Datos de ejemplo hasta conectar métricas reales desde la BD.
     */
    public function index(): View
    {
        return view('admin.dashboard', [
            'totalInstructoriasmes' => 48,
            'pctInstructorias' => 12,
            'totalInstructores' => 23,
            'nuevosInstructores' => 3,
            'totalCoordinadores' => 4,
            'asistenciaPromedio' => 87,
            'pctAsistencia' => -2,
            'pctPresencial' => 62,
            'pctEnLinea' => 38,
            'totalPresencial' => 30,
            'totalEnLinea' => 18,
            'semanas' => [6, 9, 7, 12, 10, 8, 11, 13],
            'semanasLabels' => ['S1', 'S2', 'S3', 'S4', 'S5', 'S6', 'S7', 'S8'],
            'instructoresRecientes' => collect([]),
            'coordinadores' => collect([]),
            'actividad' => [],
        ]);
    }
}
