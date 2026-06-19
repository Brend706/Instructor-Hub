{{--
    Lista de notificaciones del COORDINADOR (contenido interno de .notif-list).
    Se usa en dos lugares para mantener una sola fuente de verdad:
      1) layouts.coordinator (render inicial al cargar la página)
      2) Coordinator\NotificationController@feed (render para el polling AJAX)
    Recibe: $notifications (colección de DatabaseNotification).
--}}
@forelse(($notifications ?? collect()) as $notif)
    @php
        $data = $notif->data;
        $isUnread = is_null($notif->read_at);
        $kind = $data['kind'] ?? '';
        $when = \Illuminate\Support\Carbon::parse($data['created_at'] ?? $notif->created_at);
    @endphp
    <form method="POST"
          action="{{ route('coordinator.notifications.read', $notif->id) }}"
          class="notif-item-form">
        @csrf
        <button type="submit" class="notif-item {{ $isUnread ? 'is-unread' : '' }}">
            @if($kind === 'self_evaluation.submitted')
                @php
                    $instructorName = $data['instructor']['name'] ?? 'Instructor';
                    $groupName = $data['assignment']['group_name'] ?? 'Sin grupo';
                    $score = $data['result']['total_score'] ?? null;
                @endphp
                <span class="notif-icon" style="background:#DBEAFE;color:#1D4ED8">
                    <i class="ti ti-clipboard-check" aria-hidden="true"></i>
                </span>
                <span class="notif-body">
                    <span class="notif-title">
                        <strong>{{ $instructorName }}</strong> envió su autoevaluación
                    </span>
                    <span class="notif-meta">
                        Grupo: {{ $groupName }}
                        @if($score !== null) · Puntaje: {{ number_format((float) $score, 2) }}/10 @endif
                    </span>
                    <span class="notif-time">
                        {{ $when->translatedFormat('d M Y · H:i') }}
                    </span>
                </span>
            @elseif($kind === 'suspension_request.submitted')
                @php
                    $instructorName = $data['instructor']['name'] ?? 'Instructor';
                    $typeLabel = $data['request']['type_label'] ?? 'Solicitud';
                    $groupName = $data['assignment']['group_name'] ?? null;
                @endphp
                <span class="notif-icon" style="background:#FEF3C7;color:#92400E">
                    <i class="ti ti-player-pause" aria-hidden="true"></i>
                </span>
                <span class="notif-body">
                    <span class="notif-title">
                        <strong>{{ $instructorName }}</strong> envió una solicitud de suspensión
                    </span>
                    <span class="notif-meta">
                        {{ $typeLabel }}@if($groupName) · Grupo: {{ $groupName }}@endif
                    </span>
                    <span class="notif-time">
                        {{ $when->translatedFormat('d M Y · H:i') }}
                    </span>
                </span>
            @else
                <span class="notif-icon"><i class="ti ti-bell" aria-hidden="true"></i></span>
                <span class="notif-body">
                    <span class="notif-title">Nueva notificación</span>
                    <span class="notif-time">
                        {{ $when->translatedFormat('d M Y · H:i') }}
                    </span>
                </span>
            @endif

            @if($isUnread)
                <span class="notif-pill">Nuevo</span>
            @endif
        </button>
    </form>
@empty
    <div class="notif-empty">
        <i class="ti ti-bell-off" aria-hidden="true"></i>
        <p>No tienes notificaciones</p>
    </div>
@endforelse
