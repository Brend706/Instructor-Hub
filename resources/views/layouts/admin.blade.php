<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Dashboard' }} — InstructorHub</title>

    {{-- Bootstrap del tema: corre ANTES de pintar la página para evitar
         el "flash" blanco si el usuario tenía el modo oscuro activo. --}}
    <script>
        (function () {
            try {
                if (localStorage.getItem('fica_theme') === 'dark') {
                    document.documentElement.classList.add('dark');
                }
            } catch (e) { /* localStorage bloqueado */ }
        })();
    </script>

    {{-- Tabler Icons --}}
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/dist/tabler-icons.min.css">

    {{-- Google Fonts --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">

    {{-- Tailwind (via CDN para desarrollo; usar Vite en producción) --}}
    <script src="https://cdn.tailwindcss.com"></script>

    {{-- Livewire --}}
    @livewireStyles

    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    <link rel="stylesheet" href="{{ asset('css/layouts/admin.css') }}">
    <link rel="stylesheet" href="{{ asset('css/dark-mode.css') }}">

    {{-- Estilos adicionales por vista --}}
    @stack('styles')
</head>
<body>

    {{-- ═══════════════════════════════════
         SIDEBAR
    ═══════════════════════════════════ --}}
    <aside class="sidebar" id="sidebar" aria-label="Menú principal">

        <div class="sidebar-header">
            <div class="logo-mark" aria-hidden="true">
                <span class="logo-initials">IH</span>
                <span class="logo-dot"></span>
            </div>
            <div class="logo-text-wrap">
                <span class="logo-name">Instructor Hub</span>
                <span class="logo-sub">FICA · UTEC</span>
            </div>
        </div>

        <nav class="sidebar-nav">
            <div class="nav-section">
                <a href="{{ route('admin.dashboard') }}"
                   class="nav-item {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}"
                   data-label="Dashboard">
                    <i class="ti ti-layout-dashboard nav-icon" aria-hidden="true"></i>
                    <span class="nav-text">Dashboard</span>
                </a>
            </div>

            <div class="nav-section">
                <p class="nav-label">Gestión</p>
                <a href="{{ route('admin.coordinadores.index') }}"
                   class="nav-item {{ request()->routeIs('admin.coordinadores.*') ? 'active' : '' }}"
                   data-label="Coordinadores">
                    <i class="ti ti-users nav-icon" aria-hidden="true"></i>
                    <span class="nav-text">Coordinadores</span>
                    <span class="nav-badge">{{ $totalCoordinadores ?? '' }}</span>
                </a>
                <a href="{{ route('admin.instructores.index') }}"
                   class="nav-item {{ request()->routeIs('admin.instructores.*') ? 'active' : '' }}"
                   data-label="Instructores">
                    <i class="ti ti-user-check nav-icon" aria-hidden="true"></i>
                    <span class="nav-text">Instructores</span>
                    <span class="nav-badge">{{ $totalInstructores ?? '' }}</span>
                </a>
                     <a href="{{ route('admin.instructorias.index') }}"
                         class="nav-item {{ request()->routeIs('admin.instructorias.*') ? 'active' : '' }}"
                         data-label="Instructorías">
                    <i class="ti ti-calendar-event nav-icon" aria-hidden="true"></i>
                    <span class="nav-text">Instructorías</span>
                </a>
                <a href="{{ route('admin.evaluations.index') }}"
                   class="nav-item {{ request()->routeIs('admin.evaluations.*') ? 'active' : '' }}"
                   data-label="Evaluaciones">
                    <i class="ti ti-star nav-icon" aria-hidden="true"></i>
                    <span class="nav-text">Evaluaciones</span>
                </a>
                @php $adminPendingCount = \App\Models\SuspensionRequest::where('status','pending')->count(); @endphp
                <a href="{{ route('admin.suspensions.index') }}"
                   class="nav-item {{ request()->routeIs('admin.suspensions.*') ? 'active' : '' }}"
                   data-label="Solicitudes">
                    <i class="ti ti-file-alert nav-icon" aria-hidden="true"></i>
                    <span class="nav-text">Solicitudes</span>
                    @if($adminPendingCount > 0)
                        <span class="nav-badge" style="background:var(--primary);color:#fff">{{ $adminPendingCount }}</span>
                    @endif
                </a>
            </div>

            <div class="nav-section">
                <p class="nav-label">Análisis</p>
                <a href="{{ route('admin.reportes.instructores') }}"
                   class="nav-item {{ request()->routeIs('admin.reportes.instructores') ? 'active' : '' }}"
                   data-label="Desempeño">
                    <i class="ti ti-chart-bar nav-icon" aria-hidden="true"></i>
                    <span class="nav-text">Desempeño</span>
                </a>
                <a href="{{ route('admin.reportes.coordinaciones') }}"
                   class="nav-item {{ request()->routeIs('admin.reportes.coordinaciones') ? 'active' : '' }}"
                   data-label="Coordinaciones">
                    <i class="ti ti-building nav-icon" aria-hidden="true"></i>
                    <span class="nav-text">Coordinaciones</span>
                </a>
            </div>

            <div class="nav-section">
                <p class="nav-label">Sistema</p>
                {{-- Perfil del usuario autenticado (rutas profile.*); mismo destino en footer y menú superior --}}
                <a href="{{ route('profile.index') }}"
                   class="nav-item {{ request()->routeIs('profile.*') ? 'active' : '' }}"
                   data-label="Mi perfil">
                    <i class="ti ti-user-circle nav-icon" aria-hidden="true"></i>
                    <span class="nav-text">Mi perfil</span>
                </a>
            </div>
        </nav>

        <div class="sidebar-footer">
            {{-- Tarjeta inferior: enlace al perfil; rol legible vía User::roleDisplayLabel() --}}
            <a href="{{ route('profile.index') }}" class="user-card"
                title="{{ auth()->user()->name ?? 'Admin Demo' }}">
                <div class="avatar" aria-hidden="true">
                    {{ strtoupper(substr(auth()->user()->name ?? 'Admin Demo', 0, 2)) }}
                </div>
                <div class="user-info">
                    <div class="user-name">{{ auth()->user()->name ?? 'Admin Demo'}}</div>
                    <div class="user-role">{{ auth()->user()?->roleDisplayLabel() ?? 'Usuario' }}</div>
                </div>
            </a>
        </div>
    </aside>

    {{-- ═══════════════════════════════════
         ÁREA PRINCIPAL
    ═══════════════════════════════════ --}}
    <div class="main-content">

        {{-- TOPBAR --}}
        <header class="topbar">
            <button class="toggle-btn" id="toggleBtn"
                    onclick="toggleSidebar()"
                    aria-label="Colapsar menú lateral">
                <i class="ti ti-layout-sidebar" aria-hidden="true"></i>
            </button>

            {{-- Breadcrumb --}}
            <nav class="breadcrumb" aria-label="Ruta actual">
                <i class="ti ti-home" style="font-size:14px" aria-hidden="true"></i>
                @isset($breadcrumbs)
                    @foreach($breadcrumbs as $label => $url)
                        <span class="sep">/</span>
                        @if($loop->last)
                            <span class="current">{{ $label }}</span>
                        @else
                            <a href="{{ $url }}" style="color:var(--text-muted);text-decoration:none">{{ $label }}</a>
                        @endif
                    @endforeach
                @else
                    <span class="sep">/</span>
                    <span class="current">{{ $title ?? 'Dashboard' }}</span>
                @endisset
            </nav>

            <div class="topbar-right">
                {{-- Toggle tema claro/oscuro --}}
                <x-dark-toggle />

                {{-- Notificaciones (campanita) --}}
                {{-- $notifications y $notifCount vienen del View::composer de layouts.admin
                     (definido en App\Providers\AppServiceProvider::boot). --}}
                <div class="notif-wrap" id="notifWrap">
                    {{-- Botón con el icono de campana.
                         toggleNotifications(event) abre/cierra el dropdown agregando la clase 'open'. --}}
                    <button type="button" class="icon-btn" aria-label="Notificaciones" id="notifBtn"
                            onclick="toggleNotifications(event)">
                        <i class="ti ti-bell" aria-hidden="true"></i>
                        {{-- Punto rojo + contador: solo si hay no leídas (notifCount > 0). --}}
                        @if(($notifCount ?? 0) > 0)
                            <span class="notif-dot" aria-label="{{ $notifCount }} notificaciones"></span>
                            {{-- "9+" cuando hay más de 9 para no romper el badge visualmente. --}}
                            <span class="notif-count">{{ $notifCount > 9 ? '9+' : $notifCount }}</span>
                        @endif
                    </button>

                    {{-- Panel flotante con la lista de notificaciones.
                         Se muestra cuando #notifWrap tiene la clase 'open' (ver CSS). --}}
                    <div class="notif-dropdown" id="notifDropdown" role="menu" aria-label="Lista de notificaciones">
                        <div class="notif-header">
                            <span>Notificaciones</span>
                            {{-- "Marcar todas como leídas": solo aparece si hay alguna no leída.
                                 Hace POST a admin.notifications.read-all → markAllRead(). --}}
                            @if(($notifCount ?? 0) > 0)
                                <form method="POST" action="{{ route('admin.notifications.read-all') }}" style="margin:0">
                                    @csrf
                                    <button type="submit" class="notif-mark-all">Marcar todas como leídas</button>
                                </form>
                            @endif
                        </div>

                        <div class="notif-list">
                            {{-- @forelse: itera $notifications; @empty se ejecuta si la colección está vacía. --}}
                            @forelse(($notifications ?? collect()) as $notif)
                                @php
                                    // data está casteado a array por Laravel (columna `notifications.data`).
                                    $data = $notif->data;
                                    // read_at NULL = no leída → se resalta con la clase is-unread.
                                    $isUnread = is_null($notif->read_at);
                                    // 'kind' identifica el tipo lógico de la notificación.
                                    // Las viejas (sin 'kind') son del flujo "instructor creado".
                                    $kind = $data['kind'] ?? 'instructor.created';
                                    // Carbon::parse acepta el formato ISO 8601 guardado en toArray().
                                    $when = \Illuminate\Support\Carbon::parse($data['created_at'] ?? $notif->created_at);
                                @endphp
                                {{-- Cada notificación es un mini-form POST que llama a markRead($notif->id).
                                     El controlador la marca como leída y decide si redirige según el tipo. --}}
                                <form method="POST"
                                      action="{{ route('admin.notifications.read', $notif->id) }}"
                                      class="notif-item-form">
                                    @csrf
                                    <button type="submit" class="notif-item {{ $isUnread ? 'is-unread' : '' }}">
                                        @if($kind === 'ficabot.support')
                                            {{-- ── Solicitud de soporte desde FICABOT ── --}}
                                            @php
                                                // Datos de contacto preferidos por el usuario (pueden diferir de su cuenta).
                                                $contactName = $data['contact']['name'] ?? ($data['requester']['name'] ?? 'Usuario');
                                                $contactEmail = $data['contact']['email'] ?? ($data['requester']['email'] ?? '');
                                                $roleLabel = $data['requester']['role_label'] ?? 'Usuario';
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
                                                <span class="notif-meta">
                                                    "{{ \Illuminate\Support\Str::limit($question, 70) }}"
                                                </span>
                                                <span class="notif-time">
                                                    {{ $when->translatedFormat('d M Y · H:i') }}
                                                </span>
                                            </span>
                                        @else
                                            {{-- ── Instructor creado (notificación por defecto) ── --}}
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

                                        {{-- Pill "Nuevo" solo si aún no se leyó. --}}
                                        @if($isUnread)
                                            <span class="notif-pill">Nuevo</span>
                                        @endif
                                    </button>
                                </form>
                            @empty
                                {{-- Estado vacío: no hay notificaciones en BD para este admin. --}}
                                <div class="notif-empty">
                                    <i class="ti ti-bell-off" aria-hidden="true"></i>
                                    <p>No tienes notificaciones</p>
                                </div>
                            @endforelse
                        </div>
                    </div>
                </div>

                {{-- Ayuda --}}
                <button class="icon-btn" aria-label="Ayuda">
                    <i class="ti ti-help-circle" aria-hidden="true"></i>
                </button>

                {{-- Avatar / logout --}}
                <div style="position:relative">
                    <div class="topbar-avatar"
                         onclick="document.getElementById('user-dropdown').classList.toggle('open')"
                         aria-haspopup="true"
                         title="Opciones de usuario">
                        {{ strtoupper(substr(auth()->user()->name ?? 'Admin Demo', 0, 2)) }}
                    </div>
                    <div id="user-dropdown" style="
                        display:none; position:absolute; right:0; top:calc(100% + 6px);
                        background:var(--surface); border:1px solid var(--border);
                        border-radius:10px; min-width:160px; overflow:hidden; z-index:200;
                    ">
                        {{-- Acceso rápido al mismo perfil que la ruta profile.index --}}
                        <a href="{{ route('profile.index') }}" class="user-dropdown-link" style="
                            display:flex;align-items:center;gap:8px;
                            padding:10px 14px;font-size:13px;color:var(--text);
                            text-decoration:none;transition:background .15s;
                        ">
                            <i class="ti ti-user" style="font-size:15px"></i> Mi perfil
                        </a>
                        <div style="height:1px;background:var(--border)"></div>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="user-dropdown-logout" style="
                                display:flex;align-items:center;gap:8px;width:100%;
                                padding:10px 14px;font-size:13px;color:#991B1B;
                                background:transparent;border:none;cursor:pointer;
                                transition:background .15s;
                            ">
                                <i class="ti ti-logout" style="font-size:15px"></i> Cerrar sesión
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </header>

        {{-- CONTENIDO DE LA VISTA --}}
        <main class="page-wrapper">
            @yield('content')
        </main>

    </div>{{-- /main-content --}}

    @livewireScripts

    <script>
        // ── Toggle sidebar ──────────────────────────
        const STORAGE_KEY = 'fica_sidebar_collapsed';

        function toggleSidebar() {
            const collapsed = document.body.classList.toggle('sidebar-collapsed');
            document.getElementById('sidebar').classList.toggle('collapsed', collapsed);
            localStorage.setItem(STORAGE_KEY, collapsed ? '1' : '0');
        }

        // Restaurar estado al cargar
        if (localStorage.getItem(STORAGE_KEY) === '1') {
            document.body.classList.add('sidebar-collapsed');
            document.getElementById('sidebar').classList.add('collapsed');
        }

        // ── Cerrar dropdown al hacer clic afuera ────
        document.addEventListener('click', function(e) {
            const dropdown = document.getElementById('user-dropdown');
            if (!e.target.closest('[onclick*="user-dropdown"]')) {
                dropdown.style.display = 'none';
            }
            // Notificaciones: cierra si el clic fue fuera del contenedor .notif-wrap
            const notifWrap = document.getElementById('notifWrap');
            if (notifWrap && !e.target.closest('#notifWrap')) {
                notifWrap.classList.remove('open');
            }
        });

        document.querySelector('[onclick*="user-dropdown"]')?.addEventListener('click', function() {
            const d = document.getElementById('user-dropdown');
            d.style.display = d.style.display === 'none' ? 'block' : 'none';
        });

        // ── Dropdown de notificaciones ──────────────
        // Abre/cierra el panel agregando o quitando la clase 'open' en #notifWrap.
        // stopPropagation evita que el listener global del document lo cierre de inmediato.
        function toggleNotifications(e) {
            e.stopPropagation();
            document.getElementById('notifWrap')?.classList.toggle('open');
        }
        // Se expone en window para que el onclick inline del botón pueda llamarla.
        window.toggleNotifications = toggleNotifications;
    </script>

    {{-- Scripts adicionales por vista --}}
    @stack('scripts')

    @include('components.ficabot')  {{-- ← modal del chatbot con ia --}}
</body>
</html>