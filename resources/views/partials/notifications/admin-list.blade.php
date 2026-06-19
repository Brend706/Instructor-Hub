{{--
    Lista de notificaciones del ADMIN (contenido interno de .notif-list).
    Se usa en dos lugares para mantener una sola fuente de verdad:
      1) layouts.admin (render inicial al cargar la página)
      2) Admin\NotificationController@feed (render para el polling AJAX)
    Recibe: $notifications (colección de DatabaseNotification).
--}}
@forelse(($notifications ?? collect()) as $notif)
    @php
        $data = $notif->data;
        $isUnread = is_null($notif->read_at);
        $kind = $data['kind'] ?? 'instructor.created';
        $when = \Illuminate\Support\Carbon::parse($data['created_at'] ?? $notif->created_at);
    @endphp
    <form method="POST"
          action="{{ route('admin.notifications.read', $notif->id) }}"
          class="notif-item-form">
        @csrf
        <button type="submit" class="notif-item {{ $isUnread ? 'is-unread' : '' }}">
            @if($kind === 'ficabot.support')
                @php
                    $contactName = $data['contact']['name'] ?? ($data['requester']['name'] ?? 'Usuario');
                    $contactEmail = $data['contact']['email'] ?? ($data['requester']['email'] ?? '');
                    $roleLabel = $data['requester']['role_label'] ?? 'Usuario';
                    $reason = $data['reason'] ?? null;
                    $question = $data['question'] ?? '';
                @endphp
                <span class="notif-icon" style="background:#FEF3C7;color:#92400E">
                    <i class="ti ti-lifebuoy" aria-hidden="true"></i>
                </span>
                <span class="notif-body">
                    <span class="notif-title">
                        Soporte solicitado por <strong>{{ $contactName }}</strong>
                    </span>
                    <span class="notif-meta">
                        {{ $roleLabel }}
                        @if($contactEmail) · <a href="mailto:{{ $contactEmail }}" style="color:inherit;text-decoration:underline">{{ $contactEmail }}</a> @endif
                    </span>
                    @if($reason)
                        <span class="notif-meta">
                            <strong>Motivo:</strong> {{ \Illuminate\Support\Str::limit($reason, 90) }}
                        </span>
                    @endif
                    <span class="notif-time">
                        {{ $when->translatedFormat('d M Y · H:i') }}
                    </span>
                </span>
            @else
                @php
                    $instructorName = $data['instructor']['name'] ?? 'Instructor';
                    $creatorName = $data['creator']['name'] ?? 'Coordinador';
                @endphp
                <span class="notif-icon"><i class="ti ti-user-plus" aria-hidden="true"></i></span>
                <span class="notif-body">
                    <span class="notif-title">Nuevo instructor: <strong>{{ $instructorName }}</strong></span>
                    <span class="notif-meta">
                        Creado por <strong>{{ $creatorName }}</strong>
                    </span>
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
