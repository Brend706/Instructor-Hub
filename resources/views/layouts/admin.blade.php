<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Dashboard' }} — InstructorHub</title>

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

    {{-- Estilos adicionales por vista --}}
    @stack('styles')
</head>
<body>

    {{-- ═══════════════════════════════════
         SIDEBAR
    ═══════════════════════════════════ --}}
    <aside class="sidebar" id="sidebar" aria-label="Menú principal">

        <div class="sidebar-header">
            <div class="logo-mark" aria-hidden="true">FI</div>
            <span class="logo-text">Instructor Hub</span>
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
                <a href=""
                   class="nav-item {{ request()->routeIs('admin.instructorias.*') ? 'active' : '' }}"
                   data-label="Instructorías">
                    <i class="ti ti-calendar-event nav-icon" aria-hidden="true"></i>
                    <span class="nav-text">Instructorías</span>
                </a>
            </div>

            <div class="nav-section">
                <p class="nav-label">Análisis</p>
                <a href=""
                   class="nav-item {{ request()->routeIs('admin.reportes.*') ? 'active' : '' }}"
                   data-label="Reportes">
                    <i class="ti ti-chart-bar nav-icon" aria-hidden="true"></i>
                    <span class="nav-text">Reportes</span>
                </a>
                <a href=""
                   class="nav-item {{ request()->routeIs('admin.reportes.asistencia') ? 'active' : '' }}"
                   data-label="Asistencia">
                    <i class="ti ti-clipboard-check nav-icon" aria-hidden="true"></i>
                    <span class="nav-text">Asistencia</span>
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
            <a href="{{ route('profile.index') }}" class="user-card">
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
                {{-- Notificaciones --}}
                <button class="icon-btn" aria-label="Notificaciones">
                    <i class="ti ti-bell" aria-hidden="true"></i>
                    @if(($notifCount ?? 0) > 0)
                        <span class="notif-dot" aria-label="{{ $notifCount }} notificaciones"></span>
                    @endif
                </button>

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
                        <a href="{{ route('profile.index') }}" style="
                            display:flex;align-items:center;gap:8px;
                            padding:10px 14px;font-size:13px;color:var(--text);
                            text-decoration:none;transition:background .15s;
                        " onmouseover="this.style.background='var(--bg)'" onmouseout="this.style.background='transparent'">
                            <i class="ti ti-user" style="font-size:15px"></i> Mi perfil
                        </a>
                        <div style="height:1px;background:var(--border)"></div>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" style="
                                display:flex;align-items:center;gap:8px;width:100%;
                                padding:10px 14px;font-size:13px;color:#991B1B;
                                background:transparent;border:none;cursor:pointer;
                                transition:background .15s;
                            " onmouseover="this.style.background='#FEE2E2'" onmouseout="this.style.background='transparent'">
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
        });

        document.querySelector('[onclick*="user-dropdown"]')?.addEventListener('click', function() {
            const d = document.getElementById('user-dropdown');
            d.style.display = d.style.display === 'none' ? 'block' : 'none';
        });
    </script>

    {{-- Scripts adicionales por vista --}}
    @stack('scripts')

</body>
</html>