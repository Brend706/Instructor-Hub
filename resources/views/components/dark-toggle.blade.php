{{--
    Botón para alternar tema claro/oscuro.
    Se usa como <x-dark-toggle /> en la topbar de los 3 layouts (admin,
    coordinator, instructor). El estado real lo maneja la clase `.dark`
    en <html>, que se aplica desde un script inline en el <head> (para
    evitar el flash blanco al cargar) y se togglea con este botón.
    La preferencia se persiste en `localStorage.fica_theme`.

    Reutiliza la clase `.icon-btn` del topbar (admin.css) para mantener
    el mismo tamaño/borde que la campana y el botón de ayuda.
--}}
<button id="themeToggleBtn"
        type="button"
        class="icon-btn"
        aria-label="Cambiar tema"
        title="Cambiar tema">
    {{-- Sol: visible en claro; oculto en oscuro (ver dark-mode.css) --}}
    <i class="ti ti-sun theme-toggle-icon theme-toggle-sun" aria-hidden="true"></i>
    {{-- Luna: visible en oscuro --}}
    <i class="ti ti-moon theme-toggle-icon theme-toggle-moon" aria-hidden="true"></i>
</button>

@once
    @push('scripts')
        <script>
            (function () {
                var btn = document.getElementById('themeToggleBtn');
                if (!btn) return;
                btn.addEventListener('click', function () {
                    var html = document.documentElement;
                    var isDark = html.classList.toggle('dark');
                    try {
                        localStorage.setItem('fica_theme', isDark ? 'dark' : 'light');
                    } catch (e) { /* localStorage bloqueado: ignoramos */ }
                });
            })();
        </script>
    @endpush
@endonce
