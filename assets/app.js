// merberg.art v2 portal UI
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

let lastRawById = {};
let lastCardsById = {};
let refreshTimer = null;

function cardEl(id) {
  if (!grid) return null;
  return grid.querySelector(`.card[data-id="${CSS.escape(id)}"]`);
}

function el(card, key) {
  return card?.querySelector?.(`[data-k="${key}"]`) || null;
}

function typeText(target, newText) {
  if (!target) return;
  newText = String(newText ?? '');
  if (target.textContent === newText) return;
  if (newText.length > 48 || target.dataset.noType === '1') {
    target.textContent = newText;
    return;
  }
  target.textContent = newText;
}

function fetchJSON(url, opts = {}) {
  return fetch(url, { cache: 'no-store', ...opts })
    .then(async (res) => {
      const j = await res.json().catch(() => ({}));
      if (!res.ok) throw new Error(j?.error || `HTTP ${res.status}`);
      return j;
    });
}

function setLoaded() {
  document.body.classList.add('loaded');
  loadingState?.classList.add('hidden');
}

function classifyState(state, connection = null) {
  const s = String(state || '').toLowerCase();
  if (connection?.offline || s.includes('offline') || s.includes('closed')) return 'offline';
  if (connection?.stale || s.includes('stale')) return 'stale';
  if (s.includes('print')) return 'printing';
  if (s.includes('pause')) return 'paused';
  if (s.includes('error') || s.includes('fail')) return 'error';
  if (s.includes('ready') || s.includes('operational') || s.includes('standby')) return 'ready';
  return 'idle';
}

function setStatusPill(pill, state, connection = null) {
  if (!pill) return;
  pill.classList.remove('good', 'warn', 'bad');
  const c = classifyState(state, connection);
  if (c === 'offline' || c === 'error') pill.classList.add('bad');
  else if (c === 'printing' || c === 'ready') pill.classList.add('good');
  else if (c === 'paused' || c === 'stale') pill.classList.add('warn');
}

function fmtETA(sec) {
  sec = Number(sec);
  if (!sec || sec <= 0 || Number.isNaN(sec)) return '--';
  const h = Math.floor(sec / 3600);
  const m = Math.floor((sec % 3600) / 60);
  return h > 0 ? `${h}h ${m}m` : `${m}m`;
}

function connectionText(conn, err) {
  if (err) return err;
  if (!conn) return '--';
  const health = conn.health || (conn.offline ? 'offline' : conn.stale ? 'stale' : 'ok');
  const reason = conn.reason ? ` · ${conn.reason}` : '';
  return `${health}${reason}`;
}

function applyPower(card, data) {
  const pwr = data.power_state === undefined ? null : data.power_state;
  const pwrPill = el(card, 'power');
  if (!pwrPill) return;

  const norm = String(pwr || '').toLowerCase();
  let label = 'power: ?';
  pwrPill.classList.remove('power-on', 'power-off', 'power-unknown');
  card.classList.remove('poweredOff');

  if (['on', 'true', '1'].includes(norm)) {
    label = 'power: on';
    pwrPill.classList.add('power-on');
  } else if (['off', 'false', '0'].includes(norm)) {
    label = 'power: off';
    pwrPill.classList.add('power-off');
    card.classList.add('poweredOff');
  } else {
    pwrPill.classList.add('power-unknown');
  }
  pwrPill.textContent = label;
}

function applyCard(id, data) {
  const card = cardEl(id);
  if (!card || !data) return;

  const state = data.state || 'unknown';
  const stPill = el(card, 'state');
  typeText(stPill, state);
  setStatusPill(stPill, state, data.connection);
  card.dataset.state = classifyState(state, data.connection);

  typeText(el(card, 'source'), data.source || 'unknown');
  applyPower(card, data);

  const pct = data.progress === null || data.progress === undefined ? null : Number(data.progress);
  const pctText = pct !== null && !Number.isNaN(pct) ? `${Math.round(pct)}%` : '--%';
  typeText(el(card, 'progress'), pctText);
  typeText(el(card, 'eta'), data.eta_s != null ? `ETA ${fmtETA(data.eta_s)}` : '--');

  const fill = el(card, 'pbarFill');
  if (fill) fill.style.width = pct !== null && !Number.isNaN(pct) ? `${Math.max(0, Math.min(100, pct))}%` : '0%';

  typeText(el(card, 'job'), data.job || '--');
  typeText(el(card, 'file'), data.file || '--');
  typeText(el(card, 'hotend'), data.hotend || '--');
  typeText(el(card, 'bed'), data.bed || '--');
  typeText(el(card, 'connection'), connectionText(data.connection, data.error));

  const updated = el(card, 'updated');
  if (updated) updated.textContent = `updated ${new Date().toLocaleTimeString()}`;
}

async function refreshData() {
  if (!grid) return;
  try {
    document.querySelectorAll('.printerCard').forEach((c) => c.classList.add('refreshing'));
    const payload = await fetchJSON('api.php?action=cards');
    const cards = payload.cards || {};
    lastCardsById = cards;
    Object.keys(cards).forEach((id) => applyCard(id, cards[id]));

    if (Array.isArray(payload.raw)) {
      lastRawById = {};
      for (const status of payload.raw) {
        if (status?.id) lastRawById[status.id] = status;
      }
    }
    setLoaded();
  } catch (e) {
    console.error('Refresh failed:', e);
    if (loadingText) loadingText.textContent = 'CONNECTION FAILED. RETRYING...';
    setLoaded();
  } finally {
    document.querySelectorAll('.printerCard').forEach((c) => c.classList.remove('refreshing'));
  }
}

function pickPath(obj, path) {
  let cur = obj;
  for (const part of path.split('.')) {
    if (cur && typeof cur === 'object' && Object.prototype.hasOwnProperty.call(cur, part)) cur = cur[part];
    else return undefined;
  }
  return cur;
}

function firstDefined(...vals) {
  for (const v of vals) if (v !== undefined && v !== null && v !== '') return v;
  return undefined;
}

function buildFieldDetails(id, field) {
  const card = lastCardsById?.[id] || {};
  const rawWrap = lastRawById?.[id] || {};
  const raw = rawWrap?._raw ?? rawWrap;
  const cc2 = raw?.cc2dash || raw;

  const shown = {
    job: card.job,
    file: card.file,
    hotend: card.hotend,
    bed: card.bed,
  };

  let upstream;
  if (field === 'job') {
    upstream = firstDefined(
      pickPath(raw, 'objects_query.result.status.print_stats'),
      pickPath(raw, 'job'),
      pickPath(cc2, 'job'),
      pickPath(cc2, 'status'),
      pickPath(cc2, 'print_status')
    );
  } else if (field === 'file') {
    upstream = firstDefined(
      pickPath(raw, 'objects_query.result.status.print_stats.filename'),
      pickPath(raw, 'job.job.file'),
      pickPath(cc2, 'file'),
      pickPath(cc2, 'filename'),
      pickPath(cc2, 'print_status.filename')
    );
  } else if (field === 'hotend') {
    upstream = firstDefined(
      pickPath(raw, 'objects_query.result.status.extruder'),
      pickPath(raw, 'printer.temperature.tool0'),
      pickPath(cc2, 'hotend'),
      pickPath(cc2, 'nozzle'),
      pickPath(cc2, 'extruder'),
      pickPath(cc2, 'print_status.nozzle')
    );
  } else if (field === 'bed') {
    upstream = firstDefined(
      pickPath(raw, 'objects_query.result.status.heater_bed'),
      pickPath(raw, 'printer.temperature.bed'),
      pickPath(cc2, 'bed'),
      pickPath(cc2, 'heater_bed'),
      pickPath(cc2, 'print_status.bed')
    );
  }

  return {
    shown: shown[field] ?? '—',
    upstream: typeof upstream === 'object' ? upstream : (upstream !== undefined ? { value: upstream } : undefined),
  };
}

function showModal(show) {
  if (!rawModal) return;
  rawModal.classList.toggle('hidden', !show);
  rawModal.setAttribute('aria-hidden', show ? 'false' : 'true');
}

function openModalForField(card, field, label) {
  const id = card?.dataset?.id;
  if (!id || !rawTitle || !rawBody) return;
  const name = card.querySelector('.cardTitle')?.textContent?.trim() || id;
  rawTitle.textContent = `${name}: ${label || field}`;

  const details = buildFieldDetails(id, field);
  const lines = [`Card value: ${details.shown}`];
  lines.push('', 'Upstream (trimmed):');
  lines.push(details.upstream !== undefined ? JSON.stringify(details.upstream, null, 2) : 'not available in cached raw payload');
  rawBody.textContent = lines.join('\n');
  showModal(true);
}

function openModalForCard(card) {
  const id = card?.dataset?.id;
  if (!id || !rawTitle || !rawBody) return;
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
    .then((j) => { rawBody.textContent = JSON.stringify(j.raw || j, null, 2); })
    .catch((e) => { rawBody.textContent = `Error: ${String(e.message || e)}`; });
}

refreshBtn?.addEventListener('click', () => refreshData());

grid?.addEventListener('click', (e) => {
  const fieldBtn = e.target?.closest?.('[data-action="field"]');
  if (fieldBtn) {
    const card = fieldBtn.closest('.card');
    openModalForField(card, fieldBtn.dataset.field || '', fieldBtn.dataset.label || '');
    return;
  }
  const rawBtn = e.target?.closest?.('[data-action="raw"]');
  if (rawBtn) openModalForCard(rawBtn.closest('.card'));
});

rawModal?.addEventListener('click', (e) => {
  if (e.target?.closest?.('[data-action="close"]')) showModal(false);
});
window.addEventListener('keydown', (e) => {
  if (e.key === 'Escape') showModal(false);
});

/* ----------------------------- News Feed ------------------------------- */
(function initNews() {
  const container = document.getElementById('newsContainer');
  const contentEl = document.getElementById('newsContent');
  if (!container || !contentEl) return;

  let newsItems = [];
  try { newsItems = JSON.parse(container.dataset.news || '[]'); }
  catch { newsItems = ['News feed offline.']; }
  if (!Array.isArray(newsItems) || !newsItems.length) return;

  const animClasses = ['newsAnim-fade', 'newsAnim-slideRight', 'newsAnim-digitize'];
  let lastIdx = -1;
  let statusCounter = 0;
  let statusThreshold = 2 + Math.floor(Math.random() * 3);

  function getPrinterStatusMsg() {
    if (!printers.length || !Object.keys(lastCardsById).length) return null;
    const activeIds = Object.keys(lastCardsById).filter((id) => String(lastCardsById[id].state || '').toLowerCase().includes('print'));
    const targetId = activeIds.length ? activeIds[Math.floor(Math.random() * activeIds.length)] : printers[Math.floor(Math.random() * printers.length)]?.id;
    if (!targetId || !lastCardsById[targetId]) return null;

    const pConfig = printers.find((p) => p.id === targetId);
    const pName = pConfig ? (pConfig.name || pConfig.id) : targetId;
    const card = lastCardsById[targetId];
    const st = String(card.state || 'unknown');
    let msg = `[${pName}] ${st.charAt(0).toUpperCase() + st.slice(1)}`;
    if (st.toLowerCase().includes('print')) {
      const pct = card.progress != null ? Math.round(card.progress) : 0;
      msg += ` ${pct}%`;
      if (card.eta_s) msg += ` (ETA ${fmtETA(card.eta_s)})`;
    }
    return msg;
  }

  function rotateNews() {
    let text = null;
    if (statusCounter >= statusThreshold) {
      text = getPrinterStatusMsg();
      if (text) {
        statusCounter = 0;
        statusThreshold = 3 + Math.floor(Math.random() * 5);
      }
    }
    if (!text) {
      let idx = Math.floor(Math.random() * newsItems.length);
      if (newsItems.length > 1 && idx === lastIdx) idx = (idx + 1) % newsItems.length;
      lastIdx = idx;
      text = newsItems[idx];
      statusCounter++;
    }

    const anim = animClasses[Math.floor(Math.random() * animClasses.length)];
    contentEl.className = 'newsContent';
    void contentEl.offsetWidth;
    contentEl.textContent = text;
    contentEl.classList.add(anim);
  }

  rotateNews();
  setInterval(rotateNews, 8000);
})();

/* -------------------------- Stats Ticker ----------------------------- */
(function initStatsTicker() {
  const ticker = document.getElementById('statsTicker');
  if (!ticker) return;
  let stats = { cpu: '--', ram: '--' };

  async function updateStats() {
    try { stats = await fetchJSON('api.php?action=system_stats'); }
    catch (e) { console.error('Stats fetch error', e); }
  }

  updateStats();
  setInterval(updateStats, 10000);

  let step = 0;
  function cycle() {
    const now = new Date();
    step = (step + 1) % 4;
    const text = [
      now.toLocaleTimeString([], { hour12: false }),
      now.toLocaleDateString(),
      `CPU: ${stats.cpu || '--'}`,
      `RAM: ${stats.ram || '--'}`,
    ][step];
    typeText(ticker, text);
  }
  cycle();
  setInterval(cycle, 3500);
})();

/* --------------------------- boot ---------------------------- */
if (grid) {
  if (loadingText) loadingText.textContent = 'ESTABLISHING UPLINK...';
  refreshData();
  refreshTimer = setInterval(refreshData, 5000);
} else {
  window.addEventListener('load', setLoaded);
}
