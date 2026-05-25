{{--
    Lumi (alias técnico FICABOT) — Widget de chat con asistente rule-based (sin OpenAI).
    Se incluye al final de cada layout autenticado:
        @include('components.ficabot')

    Estructura:
      - Botón flotante (#sage-fab) abajo a la derecha que abre el modal.
      - Modal (#sage-modal) con:
          · Header  (nombre del bot + cerrar)
          · Estado vacío: saludo + sugerencias rápidas + chips de temas
          · Conversación (.sage-conversation) que se llena al enviar mensajes
          · Indicador de "escribiendo" (.sage-typing)
          · Chips de sugerencias clicables después de cada respuesta
          · Botón "Contactar administrador" (.sage-escalate) cuando el bot no sabe
          · Caja de input + botón enviar
      - Badge de notificación (#sage-badge)

    Comunicación con backend:
      - POST {{ route('ficabot.ask') }}     → {message, history} → {reply, can_escalate, suggestions[]}
      - POST {{ route('ficabot.support') }} → {contact_name, contact_email, question, bot_reply} → {ok, message}

    Las rutas viven FUERA de auth (ver routes/web.php). El bot responde con un
    banco de respuestas estático (App\Services\FicabotKnowledgeBase) y no
    requiere internet ni API keys externas.
--}}

<link rel="stylesheet" href="{{ asset('css/ficabot.css') }}">

<div id="sage-overlay" aria-hidden="true"></div>

<div id="sage-modal" role="dialog" aria-modal="true" aria-label="Lumi - Asistente de Instructor Hub">

  <div class="sage-header">
    <div class="sage-avatar-wrap">
      <div class="sage-avatar">L</div>
      <div>
        <p class="sage-name">Lumi</p>
        <p class="sage-status"><span class="sage-dot"></span> En línea · Asistente de Instructor Hub</p>
      </div>
    </div>
    <button class="sage-close-btn" id="sage-close" aria-label="Cerrar Lumi">
      <svg viewBox="0 0 14 14" fill="none" width="12" height="12">
        <path d="M1 1l12 12M13 1L1 13" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
      </svg>
    </button>
  </div>

  @php
      // Chips iniciales y "topic pills" personalizados por rol del usuario logueado.
      // El widget pregunta al bot directamente cuando se pulsa cualquiera de estos.
      $kb = app(\App\Services\FicabotKnowledgeBase::class);
      $ficabotRole = auth()->user()?->roleSlug();
      $ficabotChips = $kb->initialChips($ficabotRole);
      $ficabotTopics = $kb->topicPills($ficabotRole);

      // Pequeño mapa de iconos para los chips iniciales. Cada chip indica
      // su 'icon' en FicabotKnowledgeBase::initialChips() y aquí elegimos
      // el SVG correspondiente.
      $ficabotIcons = [
          'info'     => '<path d="M10 2a8 8 0 100 16A8 8 0 0010 2zM9 6h2v5H9V6zm0 6h2v2H9v-2z" fill="rgba(76,143,212,0.9)"/>',
          'lock'     => '<path d="M6 9V7a4 4 0 118 0v2h1v8H5V9h1zm2 0h4V7a2 2 0 10-4 0v2z" fill="rgba(194,217,244,0.8)"/>',
          'star'     => '<path d="M10 2l2.4 5 5.6.8-4 4 1 5.6L10 15l-5 2.4 1-5.6-4-4 5.6-.8L10 2z" fill="rgba(76,143,212,0.85)"/>',
          'bell'     => '<path d="M10 3a4 4 0 00-4 4v3l-1.5 2v1h11v-1L14 10V7a4 4 0 00-4-4zm0 14a2 2 0 002-2H8a2 2 0 002 2z" fill="rgba(251,191,36,0.9)"/>',
          'trash'    => '<path d="M7 4V3h6v1h3v2H4V4h3zm-1 4h8l-1 9H7L6 8z" fill="rgba(239,68,68,0.85)"/>',
          'upload'   => '<path d="M10 3l4 4h-3v6H9V7H6l4-4zM4 15h12v2H4v-2z" fill="rgba(76,143,212,0.85)"/>',
          'link'     => '<path d="M8.5 11.5l3-3M7 13a3 3 0 010-4l2-2a3 3 0 014 4l-1 1m-2 2a3 3 0 010 4l-2 2a3 3 0 01-4-4l1-1" stroke="rgba(76,143,212,0.9)" stroke-width="1.5" stroke-linecap="round" fill="none"/>',
          'qr'       => '<path d="M3 3h5v5H3V3zm9 0h5v5h-5V3zM3 12h5v5H3v-5zm9 0h2v2h-2v-2zm3 0h2v2h-2v-2zm-3 3h2v2h-2v-2zm3 0h2v2h-2v-2z" fill="rgba(76,143,212,0.9)"/>',
          'list'     => '<path d="M4 4h12v2H4V4zm0 4h12v2H4V8zm0 4h7v2H4v-2z" fill="rgba(194,217,244,0.7)"/>',
          'download' => '<path d="M10 3v8m0 0l-3-3m3 3l3-3M4 15h12v2H4v-2z" stroke="rgba(34,197,94,0.9)" stroke-width="1.6" stroke-linecap="round" fill="none"/>',
      ];
  @endphp

  {{-- Bloque inicial (saludo + sugerencias). Se oculta al enviar el primer mensaje. --}}
  <div id="sage-empty">
    <div class="sage-greeting">
      <p class="sage-greeting-title">Soy <strong>Lumi</strong>,<br>¿en qué te ayudo hoy?</p>
      <p class="sage-greeting-sub">
        @if($ficabotRole === 'admin')
          Estás en el panel de administrador. Elige un tema o cuéntame tu duda.
        @elseif($ficabotRole === 'coordinator')
          Estás en el panel de coordinador. Elige un tema o cuéntame tu duda.
        @elseif($ficabotRole === 'instructor')
          Estás en el panel de instructor. Elige un tema o cuéntame tu duda.
        @else
          Cuéntame sobre la plataforma o elige uno de los temas.
        @endif
      </p>
    </div>

    <div class="sage-chips">
      @foreach($ficabotChips as $chip)
        <button class="sage-chip" type="button">
          <div class="sage-chip-icon" style="background:rgba(76,143,212,0.13)">
            <svg viewBox="0 0 20 20" fill="none" width="15" height="15">
              {!! $ficabotIcons[$chip['icon']] ?? $ficabotIcons['info'] !!}
            </svg>
          </div>
          <span>{{ $chip['text'] }}</span>
        </button>
      @endforeach
    </div>

    <div class="sage-topics">
      @foreach($ficabotTopics as $i => $topic)
        <button class="sage-topic-pill {{ $i === 0 ? 'active' : '' }}" type="button">{{ $topic }}</button>
      @endforeach
    </div>
  </div>

  {{-- Área de conversación: oculta hasta que haya mensajes. --}}
  <div id="sage-conversation" class="sage-conversation" aria-live="polite"></div>

  {{-- Formulario de contacto que aparece al pulsar "Contactar administrador".
       Permite que el usuario indique con qué nombre y correo quiere ser contactado.
       Por defecto se rellena con los datos de su cuenta. --}}
  <div id="sage-contact-form" class="sage-contact-form" hidden>
    <p class="sage-contact-title">Datos para que el administrador te contacte</p>
    <div class="sage-contact-field">
      <label for="sage-contact-name">Nombre</label>
      <input id="sage-contact-name" type="text" placeholder="Tu nombre"
             value="{{ auth()->user()?->name ?? '' }}" maxlength="120">
      <span class="sage-contact-error" id="sage-contact-name-err"></span>
    </div>
    <div class="sage-contact-field">
      <label for="sage-contact-email">Correo</label>
      <input id="sage-contact-email" type="email" placeholder="correo@dominio.com"
             value="{{ auth()->user()?->email ?? '' }}" maxlength="180">
      <span class="sage-contact-error" id="sage-contact-email-err"></span>
    </div>
    <div class="sage-contact-actions">
      <button type="button" class="sage-contact-cancel" id="sage-contact-cancel">Cancelar</button>
      <button type="button" class="sage-contact-submit" id="sage-contact-submit">Enviar solicitud</button>
    </div>
  </div>

  <div class="sage-input-area">
    <div class="sage-input-wrap">
      <textarea id="sage-input" class="sage-textarea" placeholder="Escribe tu pregunta…" rows="1" aria-label="Mensaje para Lumi"></textarea>
      <button class="sage-send-btn" id="sage-send" aria-label="Enviar mensaje">
        <svg viewBox="0 0 16 16" fill="none" width="14" height="14">
          <path d="M8 13V3M3 8l5-5 5 5" stroke="#fff" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
      </button>
    </div>
    <p class="sage-disclaimer">Lumi puede cometer errores · Asistente de Instructor Hub</p>
  </div>

</div>

<div id="sage-badge" aria-hidden="true">1</div>

<button id="sage-fab" aria-label="Abrir Lumi, asistente de Instructor Hub" aria-expanded="false">
  <svg viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
    <path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/>
  </svg>
</button>

<script>
  (function () {
    // ─── Referencias del DOM ────────────────────────────────────────
    const fab        = document.getElementById('sage-fab');
    const modal      = document.getElementById('sage-modal');
    const overlay    = document.getElementById('sage-overlay');
    const closeBtn   = document.getElementById('sage-close');
    const badge      = document.getElementById('sage-badge');
    const textarea   = document.getElementById('sage-input');
    const sendBtn    = document.getElementById('sage-send');
    const emptyBox   = document.getElementById('sage-empty');
    const conv       = document.getElementById('sage-conversation');

    // Form de contacto que aparece al pulsar "Contactar administrador".
    const contactForm    = document.getElementById('sage-contact-form');
    const contactName    = document.getElementById('sage-contact-name');
    const contactEmail   = document.getElementById('sage-contact-email');
    const contactNameErr = document.getElementById('sage-contact-name-err');
    const contactEmailErr= document.getElementById('sage-contact-email-err');
    const contactCancel  = document.getElementById('sage-contact-cancel');
    const contactSubmit  = document.getElementById('sage-contact-submit');

    // Endpoints expuestos por FicabotController. Se generan vía route() en Blade.
    const ASK_URL     = @json(route('ficabot.ask'));
    const SUPPORT_URL = @json(route('ficabot.support'));
    // Token CSRF para que Laravel acepte el POST.
    const CSRF        = document.querySelector('meta[name="csrf-token"]')?.content || '';

    // Historial corto que el widget manda al backend en cada turno.
    // Cada item: { role: 'user' | 'assistant', content: string }
    const history = [];
    // Última pregunta y última respuesta del turno actual.
    let lastQuestion = '';
    let lastReply = '';
    // "Solicitud pendiente" que se congela cuando el bot ofrece escalar.
    // Cuando el usuario responde "sí" y se abre el formulario, NO queremos
    // mandar al admin la pregunta "sí" — usamos esta versión congelada
    // para que el admin reciba la duda original con su contexto real.
    let pendingEscalation = null; // { question, reply }
    // Flag para que el botón "Contactar administrador" no se cree dos veces.
    let escalateMounted = false;
    // Bloquea el envío mientras el bot está pensando, evita peticiones dobles.
    let isWaiting = false;

    // ─── Abrir / cerrar modal ───────────────────────────────────────
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
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape' && modal.classList.contains('open')) closeSage();
    });

    // ─── Topic pills: cada pill envía una pregunta tipo "¿qué puedo hacer
    // con X?" para que el bot dispare el intent del tema correspondiente. ──
    document.querySelectorAll('.sage-topic-pill').forEach((pill) => {
      pill.addEventListener('click', () => {
        if (isWaiting) return;
        const topic = pill.textContent.trim();
        document.querySelectorAll('.sage-topic-pill').forEach((p) => p.classList.remove('active'));
        pill.classList.add('active');
        textarea.value = '¿Qué puedo hacer con ' + topic.toLowerCase() + '?';
        sendMessage();
      });
    });

    // ─── Auto-resize del textarea ──────────────────────────────────
    textarea.addEventListener('input', function () {
      this.style.height = 'auto';
      this.style.height = Math.min(this.scrollHeight, 80) + 'px';
    });

    // Enter envía, Shift+Enter hace salto de línea.
    textarea.addEventListener('keydown', function (e) {
      if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        sendBtn.click();
      }
    });

    // Chips iniciales (estado vacío): al clicarlos, envían el mensaje directamente
    // para que el usuario vea la respuesta sin pasos extra.
    document.querySelectorAll('.sage-chip').forEach((chip) => {
      chip.addEventListener('click', function () {
        const text = this.querySelector('span').textContent;
        textarea.value = text;
        sendMessage();
      });
    });

    // ─── Helpers de render ─────────────────────────────────────────
    /** Agrega un mensaje al hilo y devuelve el nodo creado. */
    function appendMessage(role, text) {
      // Al primer mensaje, ocultamos el estado vacío y mostramos la conversación.
      emptyBox.style.display = 'none';
      conv.classList.add('open');

      const wrap = document.createElement('div');
      wrap.className = 'sage-msg sage-msg-' + role;

      const bubble = document.createElement('div');
      bubble.className = 'sage-bubble';
      bubble.textContent = text;

      wrap.appendChild(bubble);
      conv.appendChild(wrap);

      // Borra el botón de escalar y los chips de sugerencias del turno previo
      // (los volvemos a crear si la nueva respuesta los necesita).
      removeEscalateButton();
      removeSuggestions();

      conv.scrollTop = conv.scrollHeight;
      return wrap;
    }

    /** Muestra el "…" mientras el bot piensa. Devuelve el nodo para poder removerlo. */
    function appendTyping() {
      emptyBox.style.display = 'none';
      conv.classList.add('open');

      const wrap = document.createElement('div');
      wrap.className = 'sage-msg sage-msg-assistant';
      wrap.id = 'sage-typing';
      wrap.innerHTML = `
        <div class="sage-bubble sage-typing">
          <span></span><span></span><span></span>
        </div>
      `;
      conv.appendChild(wrap);
      conv.scrollTop = conv.scrollHeight;
      return wrap;
    }

    function removeTyping() {
      document.getElementById('sage-typing')?.remove();
    }

    /** Inserta el botón "Contactar administrador" después de un mensaje del bot. */
    function mountEscalateButton() {
      if (escalateMounted) return;
      escalateMounted = true;

      const box = document.createElement('div');
      box.className = 'sage-escalate-wrap';
      box.id = 'sage-escalate-wrap';
      box.innerHTML = `
        <button type="button" class="sage-escalate-btn" id="sage-escalate-btn">
          <svg viewBox="0 0 20 20" fill="none" width="14" height="14" aria-hidden="true">
            <path d="M10 2l-7 4v6c0 4 3 6 7 6s7-2 7-6V6l-7-4z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/>
          </svg>
          Contactar administrador
        </button>
      `;
      conv.appendChild(box);
      conv.scrollTop = conv.scrollHeight;

      document.getElementById('sage-escalate-btn').addEventListener('click', sendEscalation);
    }

    function removeEscalateButton() {
      document.getElementById('sage-escalate-wrap')?.remove();
      escalateMounted = false;
    }

    /** Quita los chips de sugerencias del turno anterior. */
    function removeSuggestions() {
      document.getElementById('sage-suggestions-wrap')?.remove();
    }

    /**
     * Renderiza chips de sugerencias clicables después de la última respuesta
     * del bot. Al clicar uno, se envía esa pregunta automáticamente para
     * mantener la conversación fluida.
     */
    function mountSuggestions(suggestions) {
      removeSuggestions();
      if (!Array.isArray(suggestions) || suggestions.length === 0) return;

      const box = document.createElement('div');
      box.className = 'sage-suggestions-wrap';
      box.id = 'sage-suggestions-wrap';

      suggestions.forEach((s) => {
        if (!s) return;
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'sage-suggestion-chip';
        btn.textContent = s;
        btn.addEventListener('click', () => {
          if (isWaiting) return;
          textarea.value = s;
          sendMessage();
        });
        box.appendChild(btn);
      });

      conv.appendChild(box);
      conv.scrollTop = conv.scrollHeight;
    }

    // ─── Llamada al backend para una pregunta ──────────────────────
    async function sendMessage() {
      const msg = textarea.value.trim();
      if (!msg || isWaiting) return;

      // Pintamos el mensaje del usuario y limpiamos el input.
      appendMessage('user', msg);
      textarea.value = '';
      textarea.style.height = 'auto';
      lastQuestion = msg;

      isWaiting = true;
      sendBtn.disabled = true;
      const typing = appendTyping();

      try {
        const res = await fetch(ASK_URL, {
          method: 'POST',
          // 'same-origin' (explícito) garantiza que el navegador envíe las
          // cookies de sesión de Laravel para que el middleware `auth` lo reconozca.
          credentials: 'same-origin',
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': CSRF,
            'X-Requested-With': 'XMLHttpRequest',
          },
          body: JSON.stringify({
            message: msg,
            history: history.slice(-10),
          }),
        });

        removeTyping();

        if (!res.ok) {
          // Tratamos de extraer un mensaje útil del servidor para diagnosticar.
          // (Errores de Laravel suelen venir como { message: "..." } en JSON).
          let serverMsg = '';
          try {
            const body = await res.json();
            serverMsg = body?.message || body?.reply || '';
          } catch (_) {
            try { serverMsg = await res.text(); } catch (__) {}
          }
          appendMessage('assistant',
            'Hubo un problema al consultar al asistente (código ' + res.status + ').' +
            (serverMsg ? '\nDetalle: ' + String(serverMsg).slice(0, 240) : '') +
            '\n¿Querés que te ponga en contacto con un administrador?');
          mountEscalateButton();
          return;
        }

        const data = await res.json();
        const reply = data.reply || 'Sin respuesta del asistente.';

        // Guardamos el turno en el historial (para que el siguiente envío tenga contexto).
        history.push({ role: 'user', content: msg });
        history.push({ role: 'assistant', content: reply });
        lastReply = reply;

        appendMessage('assistant', reply);

        // Atajo de conversación: si el bot detectó un "sí" inmediatamente
        // después de un ofrecimiento de escalado, abrimos directo el
        // formulario de contacto en vez de pedirle al usuario que pulse
        // el botón "Contactar administrador". Usamos la pregunta y la
        // respuesta CONGELADAS (no el "sí" actual) para que el admin reciba
        // contexto útil.
        if (data.open_contact_form) {
          showContactForm();
          return;
        }

        // Si el bot ofrece escalado, "congelamos" esta pregunta + respuesta
        // como el contexto que se enviará al admin si el usuario dice "sí"
        // a continuación. De lo contrario, dejamos el snapshot tal cual.
        if (data.can_escalate) {
          pendingEscalation = { question: msg, reply: reply };
        }

        // Pintar chips de seguimiento sugeridos por el bot (si los hay).
        if (Array.isArray(data.suggestions) && data.suggestions.length) {
          mountSuggestions(data.suggestions);
        }

        if (data.can_escalate) {
          mountEscalateButton();
        }
      } catch (err) {
        removeTyping();
        appendMessage('assistant',
          'Hubo un problema de conexión. ¿Querés que te ponga en contacto con un administrador?');
        mountEscalateButton();
      } finally {
        isWaiting = false;
        sendBtn.disabled = false;
        textarea.focus();
      }
    }

    // ─── Escalado: mostrar el form de contacto ─────────────────────
    // Al pulsar "Contactar administrador" ya NO mandamos directo la notificación:
    // primero pedimos al usuario que confirme nombre y correo de contacto.
    function showContactForm() {
      // Limpiamos errores previos.
      contactNameErr.textContent = '';
      contactEmailErr.textContent = '';
      contactName.classList.remove('has-error');
      contactEmail.classList.remove('has-error');
      // El botón de escalado de la conversación queda quitado mientras se llena el form.
      removeEscalateButton();
      contactForm.hidden = false;
      contactName.focus();
      conv.scrollTop = conv.scrollHeight;
    }

    function hideContactForm() {
      contactForm.hidden = true;
    }

    // Validación mínima en el cliente. La definitiva la hace el servidor.
    function validateContact() {
      let ok = true;
      contactNameErr.textContent = '';
      contactEmailErr.textContent = '';
      contactName.classList.remove('has-error');
      contactEmail.classList.remove('has-error');

      if (!contactName.value.trim()) {
        contactNameErr.textContent = 'Ingresa tu nombre.';
        contactName.classList.add('has-error');
        ok = false;
      }
      const email = contactEmail.value.trim();
      if (!email) {
        contactEmailErr.textContent = 'Ingresa un correo.';
        contactEmail.classList.add('has-error');
        ok = false;
      } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
        contactEmailErr.textContent = 'Correo inválido.';
        contactEmail.classList.add('has-error');
        ok = false;
      }
      return ok;
    }

    // Envío real: arma el payload con datos de contacto + pregunta + última respuesta.
    async function submitContact() {
      if (!validateContact()) return;

      contactSubmit.disabled = true;
      contactCancel.disabled = true;
      const originalText = contactSubmit.textContent;
      contactSubmit.textContent = 'Enviando…';

      try {
        const res = await fetch(SUPPORT_URL, {
          method: 'POST',
          // Mismo motivo que en /ficabot/ask: garantizamos el envío del cookie de sesión.
          credentials: 'same-origin',
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': CSRF,
            'X-Requested-With': 'XMLHttpRequest',
          },
          body: JSON.stringify({
            contact_name: contactName.value.trim(),
            contact_email: contactEmail.value.trim(),
            // Si hay una pregunta "congelada" del momento en que el bot
            // ofreció escalar, esa es la que necesita el admin (no el "sí"
            // o el último turno corto que disparó el formulario).
            question: (pendingEscalation && pendingEscalation.question)
                || lastQuestion
                || '(sin pregunta)',
            bot_reply: (pendingEscalation && pendingEscalation.reply)
                || lastReply
                || null,
          }),
        });

        // 422: Laravel devuelve errores de validación campo a campo.
        if (res.status === 422) {
          const body = await res.json();
          const errs = body?.errors || {};
          if (errs.contact_name?.[0])  { contactNameErr.textContent  = errs.contact_name[0];  contactName.classList.add('has-error'); }
          if (errs.contact_email?.[0]) { contactEmailErr.textContent = errs.contact_email[0]; contactEmail.classList.add('has-error'); }
          contactSubmit.disabled = false;
          contactCancel.disabled = false;
          contactSubmit.textContent = originalText;
          return;
        }

        if (!res.ok) {
          contactSubmit.disabled = false;
          contactCancel.disabled = false;
          contactSubmit.textContent = originalText;
          appendMessage('assistant',
            'No se pudo enviar la solicitud (código ' + res.status + '). Intenta nuevamente más tarde.');
          return;
        }

        const data = await res.json();
        hideContactForm();
        pendingEscalation = null;
        appendMessage('assistant', data.message || 'Solicitud enviada al administrador.');
      } catch (err) {
        contactSubmit.disabled = false;
        contactCancel.disabled = false;
        contactSubmit.textContent = originalText;
        appendMessage('assistant',
          'No se pudo notificar al administrador en este momento. Intenta nuevamente más tarde.');
      }
    }

    // El botón "Contactar administrador" del hilo ahora abre el form, no envía directo.
    function sendEscalation() {
      showContactForm();
    }

    // Listeners del formulario.
    contactCancel.addEventListener('click', hideContactForm);
    contactSubmit.addEventListener('click', submitContact);
    [contactName, contactEmail].forEach((el) => {
      el.addEventListener('input', () => {
        el.classList.remove('has-error');
        const err = el === contactName ? contactNameErr : contactEmailErr;
        err.textContent = '';
      });
    });

    sendBtn.addEventListener('click', sendMessage);
  })();
</script>
