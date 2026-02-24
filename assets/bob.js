// bob.js — lightweight chat UI that talks to Bob Gateway via bob_proxy.php
// Loaded only when Bob is enabled in config.php.

(function () {
  const card = document.getElementById('bobCard');
  if (!card) return;

  const cfg = (() => {
    try { return JSON.parse(card.dataset.bob || '{}'); } catch { return {}; }
  })();

  const chat = document.getElementById('bobChat');
  const input = document.getElementById('bobInput');
  const sendBtn = document.getElementById('bobSendBtn');
  const resetBtn = document.getElementById('bobResetBtn');
  const exportBtn = document.getElementById('bobExportBtn');
  const statusEl = document.getElementById('bobStatus');
  const targetEl = document.getElementById('bobTarget');

  // Optional debug elements (rendered only when debug=true in config.php)
  const stepsPre = document.getElementById('bobStepsPre');
  const powerBtn = document.getElementById('bobPowerBtn');
  const powerPre = document.getElementById('bobPowerPre');

  let activeTarget = 'all printers';
  let session = 'default';
  let history = []; // {role:'user'|'assistant', text, ts}

  function renderSteps(obj) {
    if (!stepsPre) return;
    try {
      // Keep it readable: show a compact summary first, then raw JSON.
      const steps = (obj && obj.steps) ? obj.steps : [];
      const lines = [];
      if (Array.isArray(steps) && steps.length) {
        lines.push(`[steps] ${steps.length} event(s)`);
        for (const s of steps) {
          const name = s.tool || s.name || s.fn || 'tool';
          const blocked = s.blocked ? 'BLOCKED' : '';
          const ok = (s.ok === false || s.error) ? 'ERR' : 'OK';
          const msg = s.msg || s.message || s.error || '';
          lines.push(`- ${name} ${blocked} ${ok}${msg ? ` :: ${msg}` : ''}`.trim());
        }
        lines.push('');
      }
      lines.push(JSON.stringify(obj, null, 2));
      stepsPre.textContent = lines.join('\n');
    } catch {
      stepsPre.textContent = String(obj || '');
    }
  }

  async function fetchPower() {
    if (!powerPre) return;
    powerPre.style.display = 'block';
    powerPre.textContent = 'Loading /power…';
    try {
      const r = await fetch('bob_proxy.php?action=power', { cache: 'no-store' });
      const t = await r.text();
      if (!r.ok) throw new Error(`power ${r.status}`);
      try {
        powerPre.textContent = JSON.stringify(JSON.parse(t), null, 2);
      } catch {
        powerPre.textContent = t;
      }
    } catch (e) {
      powerPre.textContent = `[error] ${e?.message || e}`;
    }
  }

  function setStatus(text, kind, busy) {
    if (!statusEl) return;
    statusEl.textContent = text;
    statusEl.classList.remove('good', 'warn', 'bad');
    if (kind) statusEl.classList.add(kind);
    card.classList.toggle('refreshing', !!busy);
  }

  function esc(s) {
    return (s || '').replace(/[&<>"']/g, (c) => ({
      '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
    }[c]));
  }

  function addMsg(role, text, opts = {}) {
    const msg = {
      role,
      text: text || '',
      ts: opts.ts || Date.now(),
      partial: !!opts.partial
    };
    history.push(msg);

    const wrap = document.createElement('div');
    wrap.className = 'bobMsg ' + (role === 'user' ? 'user' : 'assistant');
    wrap.dataset.ts = String(msg.ts);

    wrap.innerHTML = `
      <div class="bobBubble">
        <div class="bobRole">${role === 'user' ? 'You' : esc(cfg.name || 'BOB')}</div>
        <div class="bobText">${esc(msg.text)}</div>
      </div>
    `;
    chat.appendChild(wrap);
    chat.scrollTop = chat.scrollHeight;
    return wrap;
  }

  function updateLastAssistantDelta(deltaText) {
    // Find last assistant bubble in DOM
    const nodes = chat.querySelectorAll('.bobMsg.assistant .bobText');
    const el = nodes.length ? nodes[nodes.length - 1] : null;
    if (!el) return;
    el.textContent = (el.textContent || '') + (deltaText || '');
    chat.scrollTop = chat.scrollHeight;
  }

  function renderSteps(stepsObj) {
    if (!stepsPre) return;
    const steps = Array.isArray(stepsObj?.steps) ? stepsObj.steps : null;
    if (steps) {
      const lines = steps.map((s) => {
        const tool = s?.tool || s?.name || s?.fn || 'tool';
        const blocked = s?.blocked ? 'blocked' : '';
        const ok = (s?.ok === true || s?.result === true) ? 'ok' : '';
        const err = s?.error ? `error=${s.error}` : '';
        const meta = [blocked, ok, err].filter(Boolean).join(' ');
        return `- ${tool}${meta ? ` (${meta})` : ''}`;
      });
      stepsPre.textContent = lines.join('\n');
    } else {
      // fallback: raw JSON
      stepsPre.textContent = JSON.stringify(stepsObj, null, 2);
    }
  }

  async function fetchPower() {
    if (!powerPre) return;
    powerPre.style.display = 'block';
    powerPre.textContent = 'Loading /power...';
    try {
      const r = await fetch('bob_proxy.php?action=power', { cache: 'no-store' });
      const t = await r.text();
      if (!r.ok) throw new Error(`power ${r.status}: ${t}`);
      let j;
      try { j = JSON.parse(t); } catch { j = t; }
      powerPre.textContent = typeof j === 'string' ? j : JSON.stringify(j, null, 2);
    } catch (e) {
      powerPre.textContent = `[error] ${e.message || e}`;
    }
  }

  function clearChat() {
    history = [];
    chat.innerHTML = '';
  }

  function exportChat() {
    const payload = {
      exported_at: new Date().toISOString(),
      target: activeTarget,
      session,
      messages: history
    };
    const blob = new Blob([JSON.stringify(payload, null, 2)], { type: 'application/json' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `bob_chat_${session.replace(/[^a-z0-9_-]+/gi, '_')}.json`;
    document.body.appendChild(a);
    a.click();
    a.remove();
    setTimeout(() => URL.revokeObjectURL(url), 2000);
  }

  function setTarget(printerName) {
    activeTarget = printerName || 'all printers';
    if (targetEl) targetEl.textContent = activeTarget;
    session = (printerName && printerName !== 'all printers')
      ? `printer:${printerName}`
      : 'default';
    // visually hint the user
    input?.focus?.({ preventScroll: true });
    card.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }

  async function healthCheck() {
    setStatus('connecting…', 'warn', true);
    try {
      const r = await fetch('bob_proxy.php?action=health', { cache: 'no-store' });
      if (!r.ok) throw new Error(`health ${r.status}`);
      const j = await r.json();
      if (!j || j.ok !== true) throw new Error('health not ok');
      setStatus('idle', 'good', false);
      return true;
    } catch (e) {
      console.error('BOB health failed:', e);
      setStatus('offline', 'bad', false);
      return false;
    }
  }

  // Robust SSE parser for POST streams (event: X \n data: {...}\n\n)
  async function streamChat(message) {
    setStatus('sending…', 'warn', true);

    const payload = { message, session };
    const r = await fetch('bob_proxy.php?action=stream', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload),
      cache: 'no-store'
    });

    if (!r.ok || !r.body) {
      throw new Error(`stream failed ${r.status}`);
    }

    // Start assistant bubble
    addMsg('assistant', '', { partial: true });
    setStatus('thinking…', 'warn', true);

    const reader = r.body.getReader();
    const decoder = new TextDecoder('utf-8');
    let buf = '';
    let curEvent = 'message';

    while (true) {
      const { value, done } = await reader.read();
      if (done) break;
      buf += decoder.decode(value, { stream: true });

      // consume complete lines
      let idx;
      while ((idx = buf.indexOf('\n')) !== -1) {
        const line = buf.slice(0, idx).replace(/\r$/, '');
        buf = buf.slice(idx + 1);

        if (line.startsWith('event:')) {
          curEvent = line.slice(6).trim() || 'message';
          continue;
        }
        if (line.startsWith('data:')) {
          const dataStr = line.slice(5).trim();
          if (!dataStr) continue;
          let obj = null;
          try { obj = JSON.parse(dataStr); } catch { obj = { text: dataStr }; }

          if (curEvent === 'delta' && obj && typeof obj.text === 'string') {
            updateLastAssistantDelta(obj.text);
            // keep history updated (last entry)
            const last = history.length ? history[history.length - 1] : null;
            if (last && last.role === 'assistant') last.text += obj.text;
          } else if (curEvent === 'done') {
            // finalize
            setStatus('idle', 'good', false);
          } else if (curEvent === 'steps') {
            // Debug info about tool calls, cache prefetch, blocked actions, etc.
            renderSteps(obj);
          } else if (curEvent === 'start') {
            // nothing needed
          }
          continue;
        }
        // blank line indicates dispatch boundary; ignore
      }
    }

    setStatus('idle', 'good', false);
  }

  async function send() {
    const msg = (input?.value || '').trim();
    if (!msg) return;

    input.value = '';
    addMsg('user', msg);

    try {
      await streamChat(msg);
    } catch (e) {
      console.error('BOB send failed:', e);
      addMsg('assistant', '[error] Failed to reach Bob. Try again.');
      setStatus('error', 'bad', false);
    }
  }

  function wireAskButtons() {
    // Add a small "Ask Bob" button to each printer card head (if not already)
    const cards = document.querySelectorAll('#printerGrid .card');
    cards.forEach((c) => {
      if (c.querySelector('.askBobBtn')) return;
      const titleEl = c.querySelector('.cardTitle');
      if (!titleEl) return;

      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'btn ghost tiny askBobBtn';
      btn.textContent = 'Ask Bob';
      btn.title = 'Ask Bob about this printer';

      btn.addEventListener('click', (e) => {
        e.preventDefault();
        e.stopPropagation();
        const name = (titleEl.childNodes[0]?.textContent || '').trim() || c.dataset.id || 'printer';
        setTarget(name);
      });

      // Put it in the right-hand side if present, otherwise after title
      const right = c.querySelector('.cardHead .right');
      (right || titleEl).appendChild(btn);
    });
  }

  // Events
  sendBtn?.addEventListener('click', send);
  input?.addEventListener('keydown', (e) => {
    if (e.key === 'Enter') send();
  });
  resetBtn?.addEventListener('click', () => {
    clearChat();
    setStatus('idle', 'good', false);
  });
  exportBtn?.addEventListener('click', exportChat);
  powerBtn?.addEventListener('click', fetchPower);
  powerBtn?.addEventListener('click', fetchPower);

  // Init
  (async function init() {
    // set initial target label
    if (targetEl) targetEl.textContent = activeTarget;
    wireAskButtons();
    await healthCheck();
  })();

})();