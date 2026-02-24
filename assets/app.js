// fail-soft: don't let one JS error blank the whole UI
window.addEventListener('error', (e) => {
  try { console.error('UI error:', e?.error || e?.message || e); } catch { }
});
window.addEventListener('unhandledrejection', (e) => {
  try { console.error('UI promise rejection:', e?.reason || e); } catch { }
});

const grid = document.getElementById('printerGrid');
const printers = JSON.parse(grid?.dataset?.printers || '[]');

const loadingState = document.getElementById('loadingState');
const loadingText = document.getElementById('loadingText');

const rawModal = document.getElementById('rawModal');
const rawTitle = document.getElementById('rawTitle');
const rawBody = document.getElementById('rawBody');

const refreshBtn = document.getElementById('refreshBtn');

let lastRawById = {};   // populated by cards so modal is instant
let lastCardsById = {}; // populated by cards so field popups can show card values

function el(card, key) {
  return card.querySelector(`[data-k="${key}"]`);
}

function setStatusPill(pill, state) {
  pill.classList.remove('good', 'warn', 'bad');
  const s = (state || '').toLowerCase();
  if (s.includes('error') || s.includes('offline') || s.includes('closed') || s.includes('fail')) pill.classList.add('bad');
  else if (s.includes('printing') || s.includes('ready') || s.includes('operational')) pill.classList.add('good');
  else if (s.includes('paused') || s.includes('busy') || s.includes('powered down') || s.includes('powered')) pill.classList.add('warn');
}

/**
 * High-tech typing animation for improved text replacement.
 * @param {HTMLElement} el The element to update.
 * @param {string} newText The new text content.
 */
function typeText(el, newText) {
  if (!el) return;
  const current = el.textContent;
  if (current === newText) return; // No change

  // Determine typing speed based on length (max 500ms total)
  const len = newText.length;
  // If it's a very short change (e.g. number), go fast. If long filename, go faster per char.
  const interval = Math.max(10, Math.min(50, 400 / (len || 1)));

  let i = 0;
  el.textContent = ''; // clear immediately (or could backspace... clearing is more "refresh")

  // Cursor effect?
  el.classList.add('typing');

  const timer = setInterval(() => {
    el.textContent = newText.substring(0, i + 1) + '█'; // Block cursor char
    i++;
    if (i >= len) {
      clearInterval(timer);
      el.textContent = newText;
      el.classList.remove('typing');
    }
  }, interval);
}

function classifyState(state) {
  const s = (state || '').toLowerCase();
  if (s.includes('print')) return 'printing';
  if (s.includes('pause')) return 'paused';
  if (s.includes('error') || s.includes('fail')) return 'error';
  if (s.includes('offline') || s.includes('closed')) return 'offline';
  if (s.includes('ready') || s.includes('operational')) return 'ready';
  return 'idle';
}

function fmtETA(sec) {
  if (!sec || sec <= 0) return '--';
  const h = Math.floor(sec / 3600);
  const m = Math.floor((sec % 3600) / 60);
  return h > 0 ? `${h}h ${m}m` : `${m}m`;
}



function setLoaded() {
  document.body.classList.add('loaded');
}

function applyCard(id, data) {
  const card = grid.querySelector(`.card[data-id="${id}"]`);
  if (!card || !data) return;

  // --- State ---
  const st = data.state || 'unknown';
  const stPill = el(card, 'state');
  if (stPill) {
    // Only animate text if meaningful change
    if (stPill.textContent !== st) typeText(stPill, st);
    setStatusPill(stPill, st);
  }
  card.dataset.state = classifyState(st);


// --- Power (from BOB /power via api.php) ---
const pwr = (data.power_state === undefined) ? null : data.power_state;
const pwrPill = el(card, 'power');
if (pwrPill) {
  const norm = (pwr || '').toString().toLowerCase();
  let label = 'power: ?';
  pwrPill.classList.remove('power-on','power-off','power-unknown');
  card.classList.remove('poweredOff');
  if (norm === 'on' || norm === 'true' || norm === '1') {
    label = 'power: on';
    pwrPill.classList.add('power-on');
  } else if (norm === 'off' || norm === 'false' || norm === '0') {
    label = 'power: off';
    pwrPill.classList.add('power-off');
    card.classList.add('poweredOff');
  } else {
    pwrPill.classList.add('power-unknown');
  }
  if (pwrPill.textContent !== label) pwrPill.textContent = label;
}

// If power is OFF, prefer showing that over scary "offline/error" noise.
if ((pwr || '').toString().toLowerCase() === 'off') {
  const stPill2 = el(card, 'state');
  if (stPill2) {
    const forced = 'powered down';
    if (stPill2.textContent !== forced) stPill2.textContent = forced;
    setStatusPill(stPill2, forced);
  }
}

  // --- Progress / ETA ---
  const pct = (data.progress === null || data.progress === undefined) ? null : Number(data.progress);
  const pctText = (pct != null && !Number.isNaN(pct)) ? `${Math.round(pct)}%` : '--%';
  typeText(el(card, 'progress'), pctText);

  const etaText = (data.eta_s != null) ? `ETA ${fmtETA(Number(data.eta_s))}` : '--';
  typeText(el(card, 'eta'), etaText);

  const fill = el(card, 'pbarFill');
  if (fill) {
    const w = (pct != null && !Number.isNaN(pct)) ? Math.max(0, Math.min(100, pct)) : 0;
    fill.style.width = `${w}%`;
  }

  // --- Details ---
  typeText(el(card, 'job'), data.job || '--');
  typeText(el(card, 'file'), data.file || '--');
  typeText(el(card, 'hotend'), data.hotend || '--');
  typeText(el(card, 'bed'), data.bed || '--');

  // Timestamp is always new, so maybe just set it directly to avoid constant typing distraction
  el(card, 'updated').textContent = `updated ${new Date().toLocaleTimeString()}`;
}

async function fetchJSON(url, opts = {}) {
  const res = await fetch(url, { cache: 'no-store', ...opts });
  const j = await res.json().catch(() => ({}));
  if (!res.ok) throw new Error(j?.error || `HTTP ${res.status}`);
  return j;
}

async function refreshData() {
  try {
    // Show spinner on cards
    document.querySelectorAll('.card').forEach(c => c.classList.add('refreshing'));

    // Add a fake delay so the high-tech spinner can be admired (2.5s)
    const [payload] = await Promise.all([
      fetchJSON(`api.php?action=cards`),
      new Promise(resolve => setTimeout(resolve, 2500))
    ]);

    const cards = payload.cards || {};
    lastCardsById = cards;
    Object.keys(cards).forEach((id) => applyCard(id, cards[id]));

    if (Array.isArray(payload.raw)) {
      lastRawById = {};
      for (const s of payload.raw) {
        if (s?.id) lastRawById[s.id] = s;
      }
    }

    // Small delay to ensure the eye catches the high-tech spinner before transition? 
    // Or just let it fly. Let's let it fly but ensure text was updated if we had a multi-stage process.
    setLoaded();
  } catch (e) {
    console.error('Refresh failed:', e);
    if (loadingText) loadingText.textContent = 'CONNECTION FAILED. RETRYING...';
  } finally {
    // Hide spinner on cards
    document.querySelectorAll('.card').forEach(c => c.classList.remove('refreshing'));
  }
}

function setLoaded() {
  document.body.classList.add('loaded');
  if (loadingState) loadingState.classList.add('hidden');
}

function firstDefined(...vals) {
  for (const v of vals) {
    if (v !== undefined && v !== null && v !== '') return v;
  }
  return undefined;
}

function buildFieldDetails(id, field) {
  const card = lastCardsById?.[id] || {};
  const rawWrap = lastRawById?.[id] || {};
  const raw = rawWrap?._raw ?? rawWrap;

  const shown = {
    job: card.job,
    file: card.file,
    hotend: card.hotend,
    bed: card.bed,
  };

  // Try to pull a *small* relevant slice from upstream raw payload.
  let upstream = undefined;

  if (field === 'job') {
    upstream = firstDefined(
      pickPath(raw, 'result.status.print_stats'),
      pickPath(raw, 'job'),
      pickPath(raw, 'progress'),
      pickPath(raw, 'state'),
      pickPath(raw, 'current')
    );
  } else if (field === 'file') {
    upstream = firstDefined(
      pickPath(raw, 'result.status.print_stats.filename'),
      pickPath(raw, 'job.file.name'),
      pickPath(raw, 'job.file.path'),
      pickPath(raw, 'file'),
      pickPath(raw, 'job.filename')
    );
  } else if (field === 'hotend') {
    upstream = firstDefined(
      pickPath(raw, 'result.status.extruder'),
      pickPath(raw, 'temperature.tool0'),
      pickPath(raw, 'printer.temperature.tool0'),
      pickPath(raw, 'extruder'),
      pickPath(raw, 'tool0')
    );
  } else if (field === 'bed') {
    upstream = firstDefined(
      pickPath(raw, 'result.status.heater_bed'),
      pickPath(raw, 'temperature.bed'),
      pickPath(raw, 'printer.temperature.bed'),
      pickPath(raw, 'heater_bed'),
      pickPath(raw, 'bed')
    );
  }

  // If upstream is a primitive, wrap it so the modal still reads well.
  const upstreamObj =
    (typeof upstream === 'object')
      ? upstream
      : (upstream !== undefined ? { value: upstream } : undefined);

  return {
    shown: shown[field] ?? '—',
    upstream: upstreamObj,
  };
}

function openModalForField(cardEl, field, label) {
  const id = cardEl?.dataset?.id;
  if (!id) return;

  const name = cardEl.querySelector('.cardTitle')?.textContent?.trim() || id;
  rawTitle.textContent = `${name}: ${label || field}`;

  const det = buildFieldDetails(id, field);
  const lines = [];
  lines.push(`Card value: ${det.shown}`);

  if (det.upstream !== undefined) {
    lines.push('');
    lines.push('Upstream (trimmed):');
    lines.push(JSON.stringify(det.upstream, null, 2));
  } else {
    lines.push('');
    lines.push('Upstream (trimmed): not available in cached raw payload.');
  }

  rawBody.textContent = lines.join('\n');
  showModal(true);
}

// Legacy full-raw view (still useful for debugging if you keep a button around)
function openModalForCard(cardEl) {
  const id = cardEl?.dataset?.id;
  if (!id) return;

  rawTitle.textContent = `Raw: ${id}`;

  const cached = lastRawById[id];
  if (cached) {
    rawBody.textContent = JSON.stringify(cached._raw || cached, null, 2);
    showModal(true);
    return;
  }

  rawBody.textContent = 'loading…';
  showModal(true);

  fetchJSON(`api.php?action=raw&id=${encodeURIComponent(id)}`)
    .then((j) => {
      rawBody.textContent = JSON.stringify(j.raw || j, null, 2);
    })
    .catch((e) => {
      rawBody.textContent = `Error: ${String(e.message || e)}`;
    });
}

function showModal(show) {
  if (!rawModal) return;
  rawModal.classList.toggle('hidden', !show);
  rawModal.setAttribute('aria-hidden', show ? 'false' : 'true');
}

/* ------------------------------- events -------------------------------- */

refreshBtn?.addEventListener('click', () => {
  refreshData().catch((e) => console.error(e));
});

grid?.addEventListener('click', (e) => {
  // Field-specific "?" buttons
  const fieldBtn = e.target?.closest?.('[data-action="field"]');
  if (fieldBtn) {
    const card = fieldBtn.closest('.card');
    const field = fieldBtn.dataset.field || '';
    const label = fieldBtn.dataset.label || field;
    if (card && field) openModalForField(card, field, label);
    return;
  }

  // Legacy/raw fallback (if any old buttons exist)
  const rawBtn = e.target?.closest?.('[data-action="raw"]');
  if (!rawBtn) return;
  const card = rawBtn.closest('.card');
  openModalForCard(card);
});

rawModal?.addEventListener('click', (e) => {
  const close = e.target?.closest?.('[data-action="close"]');
  if (close) showModal(false);
});
window.addEventListener('keydown', (e) => {
  if (e.key === 'Escape') showModal(false);
});

// Initial load (no polling)
if (loadingText) loadingText.textContent = 'ESTABLISHING UPLINK...';
refreshData().catch((e) => {
  console.error(e);
  if (loadingText) loadingText.textContent = 'SYSTEM ERROR';
  setLoaded();
});


/* ----------------------------- News Feed ------------------------------- */
(function initNews() {
  const container = document.getElementById('newsContainer');
  const contentEl = document.getElementById('newsContent');
  if (!container || !contentEl) return;

  let rawNews = container.dataset.news;
  let newsItems = [];
  try {
    newsItems = JSON.parse(rawNews);
  } catch (e) {
    newsItems = ["News feed offline."];
  }

  if (!newsItems || newsItems.length === 0) return;

  const animClasses = ['newsAnim-fade', 'newsAnim-slideRight', 'newsAnim-digitize'];

  let lastIdx = -1;
  let statusCounter = 0;
  let statusThreshold = 2 + Math.floor(Math.random() * 3); // Initial: 2-4 items

  function getPrinterStatusMsg() {
    if (!printers || !printers.length) return null;
    // We rely on lastCardsById which is global
    if (!lastCardsById || Object.keys(lastCardsById).length === 0) return null;

    // Pick a random printer
    // Try to find one that is active first?
    const activeIds = Object.keys(lastCardsById).filter(id => {
      const st = (lastCardsById[id].state || '').toLowerCase();
      return st.includes('print');
    });

    let targetId = null;
    if (activeIds.length > 0) {
      targetId = activeIds[Math.floor(Math.random() * activeIds.length)];
    } else {
      // Fallback to any random printer
      targetId = printers[Math.floor(Math.random() * printers.length)]?.id;
    }

    if (!targetId || !lastCardsById[targetId]) return null;

    const pConfig = printers.find(p => p.id === targetId);
    const pName = pConfig ? (pConfig.name || pConfig.id) : targetId;
    const card = lastCardsById[targetId];

    const st = (card.state || 'unknown'); // capitalize first letter?
    // Capitalize helper
    const cap = s => s.charAt(0).toUpperCase() + s.slice(1);

    let msg = `[${pName}] ${cap(st)}`;

    if (st.toLowerCase().includes('print')) {
      // Add details
      const pct = (card.progress != null) ? Math.round(card.progress) : 0;
      msg += ` ${pct}%`;
      if (card.eta_s) {
        msg += ` (ETA ${fmtETA(Number(card.eta_s))})`;
      }
    }
    return msg;
  }

  function rotateNews() {
    let text = null;

    // Check inject
    if (statusCounter >= statusThreshold) {
      const statusMsg = getPrinterStatusMsg();
      if (statusMsg) {
        text = statusMsg;
        statusCounter = 0;
        statusThreshold = 3 + Math.floor(Math.random() * 5); // Next: 3-7 items
      }
    }

    if (!text) {
      // Pick random news
      let idx = Math.floor(Math.random() * newsItems.length);
      if (newsItems.length > 1 && idx === lastIdx) {
        idx = (idx + 1) % newsItems.length;
      }
      lastIdx = idx;
      text = newsItems[idx];
      statusCounter++;
    }

    // Pick random animation
    const anim = animClasses[Math.floor(Math.random() * animClasses.length)];

    // Reset animation
    contentEl.className = 'newsContent';
    void contentEl.offsetWidth; // trigger reflow

    contentEl.textContent = text;
    contentEl.classList.add(anim);

    // Optional: High-tech "typing" for some of them?
    // Let's stick to CSS animations for cleanliness unless we want to reuse typeText
  }

  // Start rotation
  rotateNews();
  setInterval(rotateNews, 8000);

})();


/* -------------------------- Stats Ticker ----------------------------- */
(function initStatsTicker() {
  const el = document.getElementById('statsTicker');
  if (!el) return;

  let stats = { cpu: '--%', ram: '-- MB' };

  async function updateStats() {
    try {
      const j = await fetchJSON('api.php?action=system_stats');
      if (j) stats = j;
    } catch (e) {
      console.error('Stats fetch error', e);
    }
  }

  // Fetch stats every 10s
  updateStats();
  setInterval(updateStats, 10000);

  // Cycle display every 3s
  // Items: Time, Date, CPU, RAM
  let step = 0;

  function cycle() {
    step = (step + 1) % 4;
    let txt = '';
    const now = new Date();

    switch (step) {
      case 0:
        // Time
        txt = now.toLocaleTimeString([], { hour12: false });
        break;
      case 1:
        // Date
        txt = now.toLocaleDateString();
        break;
      case 2:
        // CPU
        txt = `CPU: ${stats.cpu || '--'}`;
        break;
      case 3:
        // RAM
        txt = `RAM: ${stats.ram || '--'}`;
        break;
    }

    // Type effect?
    // reuse global typeText if available, or simple textContent
    if (typeof typeText === 'function') {
      typeText(el, txt);
    } else {
      el.textContent = txt;
    }
  }

  setInterval(cycle, 3500);
})();
