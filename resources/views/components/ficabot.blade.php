{{-- ============================================================
     FICABOT — Chatbot de Instructor Hub
     Incluir al final de app.blade.php, antes de </body>:
         @include('components.ficabot')
     ============================================================ --}}

<link rel="stylesheet" href="{{ asset('css/ficabot.css') }}">
{{-- ── Overlay ── --}}

<div id="sage-overlay" aria-hidden="true"></div>

{{-- ── Modal ── --}}
<div id="sage-modal" role="dialog" aria-modal="true" aria-label="FICABOT - Asistente de Instructor Hub">

  <div class="sage-header">
    <div class="sage-avatar-wrap">
      <div class="sage-avatar">F</div>
      <div>
        <p class="sage-name">FICABOT</p>
        <p class="sage-status"><span class="sage-dot"></span> En línea</p>
      </div>
    </div>
    <button class="sage-close-btn" id="sage-close" aria-label="Cerrar FICABOT">
      <svg viewBox="0 0 14 14" fill="none" width="12" height="12">
        <path d="M1 1l12 12M13 1L1 13" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
      </svg>
    </button>
  </div>

  <div class="sage-greeting">
    <p class="sage-greeting-title">Hola, ¿en qué puedo<br><strong>guiarte hoy?</strong></p>
    <p class="sage-greeting-sub">Pregúntame sobre la plataforma o elige un tema.</p>
  </div>

  <div class="sage-chips">
    <button class="sage-chip">
      <div class="sage-chip-icon" style="background:rgba(76,143,212,0.13)">
        <svg viewBox="0 0 20 20" fill="none" width="15" height="15">
          <path d="M10 2a8 8 0 100 16A8 8 0 0010 2zM9 6h2v5H9V6zm0 6h2v2H9v-2z" fill="rgba(76,143,212,0.9)"/>
        </svg>
      </div>
      <span>¿Puedo crear una cuenta en InstructorHub?</span>
    </button>
    <button class="sage-chip">
      <div class="sage-chip-icon" style="background:rgba(194,217,244,0.1)">
        <svg viewBox="0 0 20 20" fill="none" width="15" height="15">
          <path d="M4 4h12v2H4V4zm0 4h12v2H4V8zm0 4h7v2H4v-2z" fill="rgba(194,217,244,0.7)"/>
        </svg>
      </div>
      <span>¿Qué hago si no puedo iniciar sesión?</span>
    </button>
    <button class="sage-chip">
      <div class="sage-chip-icon" style="background:rgba(76,143,212,0.1)">
        <svg viewBox="0 0 20 20" fill="none" width="15" height="15">
          <path d="M10 2l2.4 5 5.6.8-4 4 1 5.6L10 15l-5 2.4 1-5.6-4-4 5.6-.8L10 2z" fill="rgba(76,143,212,0.75)"/>
        </svg>
      </div>
      <span>¿Cómo registro los estudiantes de un grupo de clase?</span>
    </button>
  </div>

  <div class="sage-topics">
    <button class="sage-topic-pill active">Instructores</button>
    <button class="sage-topic-pill">Coordinadores</button>
    <button class="sage-topic-pill">Instructorias</button>
    <button class="sage-topic-pill">Grupos de clases</button>
    <button class="sage-topic-pill">Evaluaciones</button>
  </div>

  <div class="sage-input-area">
    <div class="sage-input-wrap">
      <textarea id="sage-input" class="sage-textarea" placeholder="Escribe tu pregunta…" rows="1" aria-label="Mensaje para Sage"></textarea>
      <button class="sage-send-btn" id="sage-send" aria-label="Enviar mensaje">
        <svg viewBox="0 0 16 16" fill="none" width="14" height="14">
          <path d="M8 13V3M3 8l5-5 5 5" stroke="#fff" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
      </button>
    </div>
    <p class="sage-disclaimer">Ficabot puede cometer errores · Instructor Hub IA</p>
  </div>

</div>

{{-- ── Unread badge ── --}}
<div id="sage-badge" aria-hidden="true">1</div>

{{-- ── FAB trigger ── --}}
<button id="sage-fab" aria-label="Abrir Ficabot, asistente de Instructor Hub" aria-expanded="false">
  <svg viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
    <path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/>
  </svg>
</button>

<script>
  (function () {
    const fab     = document.getElementById('sage-fab');
    const modal   = document.getElementById('sage-modal');
    const overlay = document.getElementById('sage-overlay');
    const closeBtn= document.getElementById('sage-close');
    const badge   = document.getElementById('sage-badge');
    const textarea= document.getElementById('sage-input');

    function openSage() {
      modal.classList.add('open');
      overlay.classList.add('open');
      fab.setAttribute('aria-expanded', 'true');
      badge.classList.add('hidden');
      textarea.focus();
    }

    function closeSage() {
      modal.classList.remove('open');
      overlay.classList.remove('open');
      fab.setAttribute('aria-expanded', 'false');
    }

    fab.addEventListener('click', openSage);
    closeBtn.addEventListener('click', closeSage);
    overlay.addEventListener('click', closeSage);

    // Escape key closes modal
    document.addEventListener('keydown', e => {
      if (e.key === 'Escape' && modal.classList.contains('open')) closeSage();
    });

    // Topic pills toggle
    document.querySelectorAll('.sage-topic-pill').forEach(pill => {
      pill.addEventListener('click', () => {
        document.querySelectorAll('.sage-topic-pill').forEach(p => p.classList.remove('active'));
        pill.classList.add('active');
      });
    });

    // Auto-resize textarea
    textarea.addEventListener('input', function () {
      this.style.height = 'auto';
      this.style.height = Math.min(this.scrollHeight, 80) + 'px';
    });

    // Send on Enter (Shift+Enter = newline)
    textarea.addEventListener('keydown', function (e) {
      if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        document.getElementById('sage-send').click();
      }
    });

    // Placeholder send action — conectar a tu backend aquí
    document.getElementById('sage-send').addEventListener('click', function () {
      const msg = textarea.value.trim();
      if (!msg) return;
      // TODO: enviar msg a tu API / endpoint de IA
      console.log('[Sage] Mensaje:', msg);
      textarea.value = '';
      textarea.style.height = 'auto';
    });

    // Suggestion chips fill the input
    document.querySelectorAll('.sage-chip').forEach(chip => {
      chip.addEventListener('click', function () {
        textarea.value = this.querySelector('span').textContent;
        textarea.focus();
        textarea.dispatchEvent(new Event('input'));
      });
    });
  })();
</script>