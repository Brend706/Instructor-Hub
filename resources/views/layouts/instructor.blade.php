<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Dashboard' }} — InstructorHub</title>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/dist/tabler-icons.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/flowbite@2.3.0/dist/flowbite.min.css" rel="stylesheet">

    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    <link rel="stylesheet" href="{{ asset('css/layouts/admin.css') }}"> <!-- usa los mismos disenos del menu del admin -->

    @livewireStyles
    @stack('styles')
</head>
<body>

    <aside class="sidebar" id="sidebar" aria-label="Menu principal">

        <div class="sidebar-header">
            <div class="logo-mark" aria-hidden="true">FICA</div>
            <span class="logo-text">Instructor Hub</span>
        </div>

        <nav class="sidebar-nav">
            <div class="nav-section">
                <a href="{{ route('instructor.dashboard') }}"
                   class="nav-item {{ request()->routeIs('instructor.dashboard') ? 'active' : '' }}"
                   data-label="Dashboard">
                    <i class="ti ti-layout-dashboard nav-icon" aria-hidden="true"></i>
                    <span class="nav-text">Dashboard</span>
                </a>
            </div>

            <div class="nav-section">
                <p class="nav-label">Mis instructorías</p>
                    <a class="nav-item" data-label="Mis grupos" onclick="setActive(this)">
                        <i class="ti ti-books nav-icon"></i>
                        <span class="nav-text">Mis grupos</span>
                        <span class="nav-badge">1</span>
                    </a>
                    <a href="{{ route('instructor.session') }}"
                       class="nav-item {{ request()->routeIs('instructor.session') ? 'active' : '' }}"
                       data-label="Iniciar sesión">
                        <i class="ti ti-player-play nav-icon"></i>
                        <span class="nav-text">Iniciar sesión</span>
                    </a>
                    <a class="nav-item" data-label="Asistencia" onclick="setActive(this)">
                        <i class="ti ti-clipboard-check nav-icon"></i>
                        <span class="nav-text">Asistencia</span>
                    </a>
            </div>

            <div class="nav-section">
                <p class="nav-label">Cuenta</p>
                <a href="{{ route('profile.index') }}"
                   class="nav-item {{ request()->routeIs('profile.*') ? 'active' : '' }}"
                   data-label="Mi perfil">
                    <i class="ti ti-user-circle nav-icon" aria-hidden="true"></i>
                    <span class="nav-text">Mi perfil</span>
                </a>
            </div>
        </nav>

        <div class="sidebar-footer">
            <a href="" class="user-card">
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
                <button class="icon-btn" aria-label="Notificaciones">
                    <i class="ti ti-bell" aria-hidden="true"></i>
                    @if(($notifCount ?? 0) > 0)
                        <span class="notif-dot"></span>
                    @endif
                </button>
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
                        <a href="" style="display:flex;align-items:center;gap:8px;padding:10px 14px;font-size:13px;color:var(--text);text-decoration:none;transition:background .15s" onmouseover="this.style.background='var(--bg)'" onmouseout="this.style.background='transparent'">
                            <i class="ti ti-user" style="font-size:15px"></i> Mi perfil
                        </a>
                        <div style="height:1px;background:var(--border)"></div>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" style="display:flex;align-items:center;gap:8px;width:100%;padding:10px 14px;font-size:13px;color:#991B1B;background:transparent;border:none;cursor:pointer;transition:background .15s" onmouseover="this.style.background='#FEE2E2'" onmouseout="this.style.background='transparent'">
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
    </script>

    @stack('scripts')

</body>
</html>