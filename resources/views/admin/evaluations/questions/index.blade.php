@extends('layouts.admin', ['title' => 'Plantillas de preguntas'])

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/admin/evaluations.css') }}">
    <link rel="stylesheet" href="{{ asset('css/admin/evaluation-questions.css') }}">
@endpush

@section('content')

<a href="{{ route('admin.evaluations.index') }}" class="back-link">
    <i class="ti ti-arrow-left" aria-hidden="true"></i> Volver a evaluaciones
</a>

<div class="page-header">
    <div>
        <h1 class="page-title">Plantillas de preguntas</h1>
        <p class="page-sub">
            Edita el catálogo de preguntas que usan los formularios de evaluación.
            Los cambios se aplican al instante a todos los formularios pendientes.
        </p>
    </div>
</div>

@if(session('status'))
    <div class="ev-flash-success">
        <i class="ti ti-circle-check" aria-hidden="true"></i> {{ session('status') }}
    </div>
@endif

@if($errors->any())
    <div class="ev-flash-error">
        <i class="ti ti-alert-triangle" aria-hidden="true"></i>
        <div>
            <strong>Revisá los siguientes campos:</strong>
            <ul>
                @foreach($errors->all() as $e)
                    <li>{{ $e }}</li>
                @endforeach
            </ul>
        </div>
    </div>
@endif

{{-- ── Tabs por tipo ──────────────────────────────────────────── --}}
<div class="qt-tabs">
    @foreach($allTypes as $t)
        <a href="{{ route('admin.evaluations.questions.index', $t->slug) }}"
           class="qt-tab {{ $t->id === $type->id ? 'is-active' : '' }}">
            <span>{{ $t->name }}</span>
            <span class="qt-tab-count">{{ $t->questions_count }}</span>
        </a>
    @endforeach
</div>

<div class="qt-grid">
    {{-- ── Lista de preguntas (orden, texto, tipo, acciones) ───── --}}
    <div class="qt-list-card">
        <div class="qt-list-head">
            <h2 class="qt-list-title">{{ $type->name }}</h2>
            <span class="qt-list-sub">{{ $questions->count() }} pregunta(s) en total</span>
        </div>

        @if($questions->isEmpty())
            <div class="qt-empty">
                <i class="ti ti-help-octagon" aria-hidden="true"></i>
                <p>Todavía no hay preguntas configuradas para este tipo. Agregá la primera con el formulario de la derecha.</p>
            </div>
        @else
            <ol class="qt-list">
                @foreach($questions as $q)
                    <li class="qt-item {{ $q->is_active ? '' : 'qt-item-inactive' }}">
                        <div class="qt-item-order">{{ $loop->iteration }}</div>

                        <div class="qt-item-body" data-q-id="{{ $q->id }}">
                            {{-- Vista normal --}}
                            <div class="qt-item-view">
                                <div class="qt-item-text">{{ $q->question_text }}</div>
                                <div class="qt-item-meta">
                                    @if($q->question_type === 'score')
                                        <span class="qt-badge qt-badge-score">
                                            <i class="ti ti-star"></i>
                                            Score 1–{{ $q->max_score ?? 10 }}
                                        </span>
                                    @else
                                        <span class="qt-badge qt-badge-text">
                                            <i class="ti ti-message"></i>
                                            Texto libre
                                        </span>
                                    @endif

                                    @if(! $q->is_active)
                                        <span class="qt-badge qt-badge-inactive">Inactiva</span>
                                    @endif

                                    @if($q->answers_count > 0)
                                        <span class="qt-badge qt-badge-info" title="Respuestas históricas">
                                            <i class="ti ti-database"></i>
                                            {{ $q->answers_count }} resp.
                                        </span>
                                    @endif
                                </div>
                            </div>

                            {{-- Formulario inline de edición (oculto por defecto) --}}
                            <form method="POST"
                                  action="{{ route('admin.evaluations.questions.update', $q) }}"
                                  class="qt-item-edit"
                                  hidden>
                                @csrf
                                @method('PUT')
                                <textarea name="question_text" rows="2" maxlength="500" required>{{ $q->question_text }}</textarea>
                                <div class="qt-edit-row">
                                    <select name="question_type" class="js-qt-type">
                                        <option value="score" {{ $q->question_type === 'score' ? 'selected' : '' }}>Score</option>
                                        <option value="text"  {{ $q->question_type === 'text'  ? 'selected' : '' }}>Texto</option>
                                    </select>
                                     <input type="number"
                                         name="max_score"
                                         min="1" max="10"
                                         value="{{ $q->max_score ?? 10 }}"
                                         class="js-qt-max"
                                         {{ $q->question_type === 'text' ? 'disabled' : '' }}>
                                    <button type="submit" class="ev-btn-primary ev-btn-sm">
                                        <i class="ti ti-check"></i> Guardar
                                    </button>
                                    <button type="button" class="ev-btn-ghost ev-btn-sm js-qt-cancel">
                                        Cancelar
                                    </button>
                                </div>
                            </form>
                        </div>

                        <div class="qt-item-actions">
                            {{-- Mover arriba / abajo --}}
                            <form method="POST" action="{{ route('admin.evaluations.questions.move', [$q, 'up']) }}">
                                @csrf
                                <button type="submit" class="qt-icon-btn" title="Subir" {{ $loop->first ? 'disabled' : '' }}>
                                    <i class="ti ti-chevron-up"></i>
                                </button>
                            </form>
                            <form method="POST" action="{{ route('admin.evaluations.questions.move', [$q, 'down']) }}">
                                @csrf
                                <button type="submit" class="qt-icon-btn" title="Bajar" {{ $loop->last ? 'disabled' : '' }}>
                                    <i class="ti ti-chevron-down"></i>
                                </button>
                            </form>

                            {{-- Editar --}}
                            <button type="button" class="qt-icon-btn js-qt-edit" title="Editar">
                                <i class="ti ti-edit"></i>
                            </button>

                            {{-- Activar / desactivar --}}
                            <form method="POST" action="{{ route('admin.evaluations.questions.toggle', $q) }}">
                                @csrf
                                <button type="submit" class="qt-icon-btn" title="{{ $q->is_active ? 'Desactivar' : 'Activar' }}">
                                    @if($q->is_active)
                                        <i class="ti ti-eye-off"></i>
                                    @else
                                        <i class="ti ti-eye"></i>
                                    @endif
                                </button>
                            </form>

                            {{-- Eliminar --}}
                            <form method="POST"
                                  action="{{ route('admin.evaluations.questions.destroy', $q) }}"
                                  onsubmit="return confirm('¿Eliminar esta pregunta? Si ya tiene respuestas registradas se desactivará en lugar de borrarse.');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="qt-icon-btn qt-icon-danger" title="Eliminar">
                                    <i class="ti ti-trash"></i>
                                </button>
                            </form>
                        </div>
                    </li>
                @endforeach
            </ol>
        @endif
    </div>

    {{-- ── Formulario "Agregar pregunta" ───────────────────────── --}}
    <aside class="qt-new-card">
        <h2 class="qt-new-title">
            <i class="ti ti-plus" aria-hidden="true"></i>
            Nueva pregunta
        </h2>
        <p class="qt-new-help">
            Se agrega al final del listado. Después podés reordenarla con las flechas.
        </p>
        <form method="POST" action="{{ route('admin.evaluations.questions.store', $type->slug) }}" class="qt-new-form">
            @csrf

            <label class="qt-label" for="new_question_text">Pregunta</label>
            <textarea id="new_question_text"
                      name="question_text"
                      rows="3"
                      maxlength="500"
                      required
                      placeholder="Ej: El instructor demuestra dominio del tema">{{ old('question_text') }}</textarea>

            <label class="qt-label" for="new_question_type">Tipo</label>
            <select name="question_type" id="new_question_type" class="js-qt-new-type">
                <option value="score" {{ old('question_type', 'score') === 'score' ? 'selected' : '' }}>Score (escala numérica)</option>
                <option value="text"  {{ old('question_type') === 'text' ? 'selected' : '' }}>Texto libre</option>
            </select>

            <label class="qt-label" for="new_max_score">Escala máxima</label>
                 <input type="number"
                     id="new_max_score"
                     name="max_score"
                     min="1" max="10"
                     value="{{ old('max_score', 10) }}"
                     class="js-qt-new-max">

            <button type="submit" class="ev-btn-primary qt-new-submit">
                <i class="ti ti-circle-plus"></i> Agregar pregunta
            </button>
        </form>
    </aside>
</div>

<script>
    // Toggle inline edición de cada pregunta y bloqueo del campo max_score
    // según el tipo elegido (score = habilitado, text = deshabilitado).
    document.addEventListener('click', function (ev) {
        const editBtn = ev.target.closest('.js-qt-edit');
        if (editBtn) {
            const body = editBtn.closest('.qt-item').querySelector('.qt-item-body');
            body.querySelector('.qt-item-view').hidden = true;
            body.querySelector('.qt-item-edit').hidden = false;
            return;
        }
        const cancelBtn = ev.target.closest('.js-qt-cancel');
        if (cancelBtn) {
            const body = cancelBtn.closest('.qt-item').querySelector('.qt-item-body');
            body.querySelector('.qt-item-view').hidden = false;
            body.querySelector('.qt-item-edit').hidden = true;
        }
    });

    // Habilita / deshabilita max_score según el tipo (en form de edición y de creación).
    document.addEventListener('change', function (ev) {
        if (ev.target.matches('.js-qt-type')) {
            const max = ev.target.closest('form').querySelector('.js-qt-max');
            if (max) max.disabled = ev.target.value !== 'score';
        }
        if (ev.target.matches('.js-qt-new-type')) {
            const max = document.querySelector('.js-qt-new-max');
            if (max) max.disabled = ev.target.value !== 'score';
        }
    });
</script>

@endsection
