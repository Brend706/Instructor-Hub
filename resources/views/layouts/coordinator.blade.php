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

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/dist/tabler-icons.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/flowbite@2.3.0/dist/flowbite.min.css" rel="stylesheet">

    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    <link rel="stylesheet" href="{{ asset('css/layouts/admin.css') }}"> <!-- usa los mismos disenos del menu del admin -->
    <link rel="stylesheet" href="{{ asset('css/dark-mode.css') }}">

    @livewireStyles
    @stack('styles')
</head>
<body>

    <aside class="sidebar" id="sidebar" aria-label="Menu principal">

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
                <a href="{{ route('coordinator.dashboard') }}"
                   class="nav-item {{ request()->routeIs('coordinator.dashboard') ? 'active' : '' }}"
                   data-label="Dashboard">
                    <i class="ti ti-layout-dashboard nav-icon" aria-hidden="true"></i>
                    <span class="nav-text">Dashboard</span>
                </a>
            </div>

            @php
                // Métricas del sidebar — se calculan en línea para funcionar en cualquier página del coordinador
                $_coordId = \App\Models\Coordinator::where('user_id', auth()->id())->value('id') ?? -1;

                $_sidebarInstCount = \App\Models\Instructor::where('coordinator_id', $_coordId)->count();

                $_coordTypeId = \Illuminate\Support\Facades\DB::table('evaluation_types')
                    ->where('slug', 'coordinator')->value('id');

                $_sidebarEvalPending = $_coordTypeId
                    ? \Illuminate\Support\Facades\DB::table('instructor_assignments')
                        ->join('instructors', 'instructor_assignments.instructor_id', '=', 'instructors.id')
                        ->where('instructors.coordinator_id', $_coordId)
                        ->where('instructor_assignments.status', 'Finalizado')
                        ->whereNotExists(function ($q) use ($_coordTypeId) {
                            $q->from('evaluation_results')
                              ->whereColumn('evaluation_results.assignment_id', 'instructor_assignments.id')
                              ->where('evaluation_results.evaluation_type_id', $_coordTypeId);
                        })
                        ->count()
                    : 0;

                $pendingSuspCount = \App\Models\SuspensionRequest::query()
                    ->whereHas('instructor', fn($q) => $q->where('coordinator_id', $_coordId))
                    ->where('status', 'pending')
                    ->count();
            @endphp

            <div class="nav-section">
                <p class="nav-label">Gestion</p>
                <a href="{{ route('coordinator.instructores.index') }}"
                   class="nav-item {{ request()->routeIs('coordinator.instructores.*') ? 'active' : '' }}"
                   data-label="Mis instructores">
                    <i class="ti ti-user-check nav-icon" aria-hidden="true"></i>
                    <span class="nav-text">Mis instructores</span>
                    @if($_sidebarInstCount > 0)
                        <span class="nav-badge">{{ $_sidebarInstCount }}</span>
                    @endif
                </a>
                <a href="{{ route('coordinator.groups.index') }}"
                   class="nav-item {{ request()->routeIs('coordinator.groups.*') ? 'active' : '' }}"
                   data-label="Grupos de clase">
                    <i class="ti ti-books nav-icon" aria-hidden="true"></i>
                    <span class="nav-text">Grupos de clase</span>
                </a>
                <a href="{{ route('coordinator.instructorias.index') }}"
                   class="nav-item {{ request()->routeIs('coordinator.instructorias.*') ? 'active' : '' }}"
                   data-label="Instructorías">
                    <i class="ti ti-calendar-event nav-icon" aria-hidden="true"></i>
                    <span class="nav-text">Instructorías</span>
                </a>
                <a href="{{ route('coordinator.evaluations.index') }}"
                   class="nav-item {{ request()->routeIs('coordinator.evaluations.*') ? 'active' : '' }}"
                   data-label="Evaluaciones">
                    <i class="ti ti-star nav-icon" aria-hidden="true"></i>
                    <span class="nav-text">Evaluaciones</span>
                    @if($_sidebarEvalPending > 0)
                        <span class="nav-badge" style="background:var(--accent);color:#fff">{{ $_sidebarEvalPending }}</span>
                    @endif
                </a>
                <a href="{{ route('coordinator.suspensions.index') }}"
                   class="nav-item {{ request()->routeIs('coordinator.suspensions.*') ? 'active' : '' }}"
                   data-label="Solicitudes">
                    <i class="ti ti-file-alert nav-icon" aria-hidden="true"></i>
                    <span class="nav-text">Solicitudes</span>
                    @if($pendingSuspCount > 0)
                        <span class="nav-badge" style="background:var(--primary);color:#fff">{{ $pendingSuspCount }}</span>
                    @endif
                </a>
                <a href="{{ route('profile.index') }}"
                   class="nav-item {{ request()->routeIs('profile.*') ? 'active' : '' }}"
                   data-label="Mi perfil">
                    <i class="ti ti-user nav-icon" aria-hidden="true"></i>
                    <span class="nav-text">Mi perfil</span>
                </a>
            </div>

        </nav>

        <div class="sidebar-footer">
            <a href="{{ route('profile.index') }}" class="user-card">
                <div class="avatar" aria-hidden="true">
                    {{ strtoupper(substr(auth()->user()->name ?? 'CO', 0, 2)) }}
                </div>
                <div class="user-info">
                    <div class="user-name">{{ auth()->user()->name ?? 'Coordinador' }}</div>
                    <div class="user-role">Coordinador</div>
                </div>
            </a>
        </div>
    </aside>

    <div class="main-content">

        <header class="topbar">
            <button class="toggle-btn" id="toggleBtn"
                    onclick="toggleSidebar()"
                    aria-label="Colapsar menu lateral">
                <i class="ti ti-layout-sidebar" aria-hidden="true"></i>
            </button>

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
                <x-dark-toggle />

                {{-- Notificaciones (campanita) del coordinador.
                     $notifications y $notifCount los inyecta el View::composer
                     de layouts.coordinator en AppServiceProvider. --}}
                <div class="notif-wrap" id="notifWrap">
                    <button type="button" class="icon-btn" aria-label="Notificaciones" id="notifBtn"
                            onclick="toggleNotifications(event)">
                        <i class="ti ti-bell" aria-hidden="true"></i>
                        @if(($notifCount ?? 0) > 0)
                            <span class="notif-dot" aria-label="{{ $notifCount }} notificaciones"></span>
                            <span class="notif-count">{{ $notifCount > 9 ? '9+' : $notifCount }}</span>
                        @endif
                    </button>

                    <div class="notif-dropdown" id="notifDropdown" role="menu" aria-label="Lista de notificaciones">
                        <div class="notif-header">
                            <span>Notificaciones</span>
                            @if(($notifCount ?? 0) > 0)
                                <form method="POST" action="{{ route('coordinator.notifications.read-all') }}" style="margin:0">
                                    @csrf
                                    <button type="submit" class="notif-mark-all">Marcar todas como leídas</button>
                                </form>
                            @endif
                        </div>

                        <div class="notif-list">
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
                                            {{-- ── Autoevaluación enviada por un instructor ── --}}
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
                                        @else
                                            {{-- Fallback genérico para cualquier otra notificación futura --}}
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
                        </div>
                    </div>
                </div>

                <button class="icon-btn" aria-label="Ayuda">
                    <i class="ti ti-help-circle" aria-hidden="true"></i>
                </button>
                <div style="position:relative">
                    <div class="topbar-avatar"
                         onclick="document.getElementById('user-dropdown').classList.toggle('open')"
                         title="Opciones de usuario">
                        {{ strtoupper(substr(auth()->user()->name ?? 'CO', 0, 2)) }}
                    </div>
                    <div id="user-dropdown" style="display:none;position:absolute;right:0;top:calc(100% + 6px);background:var(--surface);border:1px solid var(--border);border-radius:10px;min-width:160px;overflow:hidden;z-index:200;">
                        <a href="{{ route('profile.index') }}" class="user-dropdown-link" style="display:flex;align-items:center;gap:8px;padding:10px 14px;font-size:13px;color:var(--text);text-decoration:none;transition:background .15s">
                            <i class="ti ti-user" style="font-size:15px"></i> Mi perfil
                        </a>
                        <div style="height:1px;background:var(--border)"></div>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="user-dropdown-logout" style="display:flex;align-items:center;gap:8px;width:100%;padding:10px 14px;font-size:13px;color:#991B1B;background:transparent;border:none;cursor:pointer;transition:background .15s">
                                <i class="ti ti-logout" style="font-size:15px"></i> Cerrar sesion
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </header>

        <main class="page-wrapper">
            @yield('content')
        </main>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <script src="https://cdn.jsdelivr.net/npm/flowbite@2.3.0/dist/flowbite.min.js"></script>
    @livewireScripts

    <script>
        const STORAGE_KEY = 'fica_coordinator_sidebar_collapsed';

        function toggleSidebar() {
            const collapsed = document.body.classList.toggle('sidebar-collapsed');
            document.getElementById('sidebar').classList.toggle('collapsed', collapsed);
            localStorage.setItem(STORAGE_KEY, collapsed ? '1' : '0');
        }

        if (localStorage.getItem(STORAGE_KEY) === '1') {
            document.body.classList.add('sidebar-collapsed');
            document.getElementById('sidebar').classList.add('collapsed');
        }

        document.addEventListener('click', function(e) {
            const dropdown = document.getElementById('user-dropdown');
            if (!e.target.closest('[onclick*="user-dropdown"]')) {
                dropdown.style.display = 'none';
            }
        });

        document.querySelector('[onclick*="user-dropdown"]')?.addEventListener('click', function() {
            const d = document.getElementById('user-dropdown');
            d.style.display = d.style.display === 'none' ? 'block' : 'none';
        });

        // Toggle del dropdown de notificaciones (campanita).
        // Usa la clase .open del CSS de layouts/admin.css (mismo estilo que el admin).
        function toggleNotifications(e) {
            e.stopPropagation();
            const wrap = document.getElementById('notifWrap');
            wrap?.classList.toggle('open');
        }
        window.toggleNotifications = toggleNotifications;

        // Cerrar el dropdown si se hace clic fuera.
        document.addEventListener('click', function (e) {
            const wrap = document.getElementById('notifWrap');
            if (wrap && !wrap.contains(e.target)) {
                wrap.classList.remove('open');
            }
        });
    </script>

    @stack('scripts')
    @include('components.ficabot')  {{-- ← modal del chatbot con ia --}}
</body>
</html>