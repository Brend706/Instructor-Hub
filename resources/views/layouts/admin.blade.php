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
                        {{-- Punto rojo + contador. El span #notifBadge está SIEMPRE en el DOM
                             (aunque esté vacío) para que el polling JS pueda actualizarlo sin
                             recargar la página. --}}
                        <span id="notifBadge">
                            @if(($notifCount ?? 0) > 0)
                                <span class="notif-dot" aria-label="{{ $notifCount }} notificaciones"></span>
                                <span class="notif-count">{{ $notifCount > 9 ? '9+' : $notifCount }}</span>
                            @endif
                        </span>
                    </button>

                    {{-- Panel flotante con la lista de notificaciones.
                         Se muestra cuando #notifWrap tiene la clase 'open' (ver CSS). --}}
                    <div class="notif-dropdown" id="notifDropdown" role="menu" aria-label="Lista de notificaciones">
                        <div class="notif-header">
                            <span>Notificaciones</span>
                            {{-- "Marcar todas como leídas": el form vive SIEMPRE en el DOM
                                 (id #notifMarkAll) y el polling JS lo muestra/oculta según
                                 haya o no notificaciones sin leer. --}}
                            <form method="POST" action="{{ route('admin.notifications.read-all') }}"
                                  id="notifMarkAll" style="margin:0;{{ ($notifCount ?? 0) > 0 ? '' : 'display:none' }}">
                                @csrf
                                <button type="submit" class="notif-mark-all">Marcar todas como leídas</button>
                            </form>
                        </div>

                        {{-- El contenido de la lista vive en un partial reutilizable para que
                             el render inicial y el del polling AJAX sean idénticos. --}}
                        <div class="notif-list" id="notifList">
                            @include('partials.notifications.admin-list', ['notifications' => $notifications ?? collect()])
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

        // ── Polling de notificaciones (sin recargar la página) ──────
        // Cada cierto tiempo consulta el feed y actualiza el punto/contador.
        // La lista solo se reemplaza cuando el dropdown está cerrado, para no
        // interrumpir al usuario si lo tiene abierto.
        (function () {
            const FEED_URL = @json(route('admin.notifications.feed'));
            const POLL_MS = 25000;

            function renderBadge(count) {
                const badge = document.getElementById('notifBadge');
                if (!badge) return;
                if (count > 0) {
                    const label = count > 9 ? '9+' : count;
                    badge.innerHTML =
                        '<span class="notif-dot" aria-label="' + count + ' notificaciones"></span>' +
                        '<span class="notif-count">' + label + '</span>';
                } else {
                    badge.innerHTML = '';
                }
                const markAll = document.getElementById('notifMarkAll');
                if (markAll) markAll.style.display = count > 0 ? '' : 'none';
            }

            async function poll() {
                try {
                    const res = await fetch(FEED_URL, {
                        headers: { 'X-Requested-With': 'XMLHttpRequest' },
                        credentials: 'same-origin',
                    });
                    if (!res.ok) return;
                    const data = await res.json();
                    renderBadge(data.count ?? 0);

                    // Solo refrescamos la lista si el panel está cerrado.
                    const wrap = document.getElementById('notifWrap');
                    const list = document.getElementById('notifList');
                    if (list && wrap && !wrap.classList.contains('open') && typeof data.html === 'string') {
                        list.innerHTML = data.html;
                    }
                } catch (e) {
                    // Silencioso: si falla una vez, lo reintenta en el próximo ciclo.
                }
            }

            setInterval(poll, POLL_MS);
        })();
    </script>

    {{-- Scripts adicionales por vista --}}
    @stack('scripts')

    @include('components.ficabot')  {{-- ← modal del chatbot con ia --}}
</body>
</html>