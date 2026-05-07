<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

//rutas temporales para visualizar solo frontend

Route::get('/admin/dashboard', function () {
    // Datos de ejemplo para el dashboard que despues seran enviados desde el backend
    return view('admin.dashboard', [
        'totalInstructoriasmes' => 48,
        'pctInstructorias'      => 12,
        'totalInstructores'     => 23,
        'nuevosInstructores'    => 3,
        'totalCoordinadores'    => 4,
        'asistenciaPromedio'    => 87,
        'pctAsistencia'         => -2,
        'pctPresencial'         => 62,
        'pctEnLinea'            => 38,
        'totalPresencial'       => 30,
        'totalEnLinea'          => 18,
        'semanas'               => [6,9,7,12,10,8,11,13],
        'semanasLabels'         => ['S1','S2','S3','S4','S5','S6','S7','S8'],
        'instructoresRecientes' => collect([]),
        'coordinadores'         => collect([]),
        'actividad'             => [],
    ]);
})->name('admin.dashboard');
Route::get('/admin/coordinadores', fn() => view('admin.coordinadores.index'))->name('admin.coordinadores.index');
Route::get('/coordinador/dashboard', fn() => view('coordinador.dashboard'))->name('coordinador.dashboard');
Route::get('/instructor/dashboard', fn() => view('instructor.dashboard'))->name('instructor.dashboard');