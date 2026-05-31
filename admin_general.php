<!doctype html>
<html lang="no">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Generell Oppbevaring Admin</title>
<style>
body { margin: 0; font-family: Arial, sans-serif; background: #f2ecf8; color: #111; }
body.cursor-hidden, body.cursor-hidden * { cursor: none !important; }
header { background: #5f2f86; color: #fff; padding: 14px 22px; position: sticky; top: 0; z-index: 10; }
.header-row { max-width: 1400px; margin: 0 auto; display: flex; align-items: center; justify-content: space-between; gap: 12px; }
.header-row h1 { margin: 0; font-size: 40px; }
.header-actions { display: flex; gap: 10px; flex-wrap: wrap; }
main { max-width: 1400px; margin: 0 auto; padding: 18px 20px 24px; display: grid; gap: 16px; }
.panel { background: #fff; border: 1px solid #dfd4ea; border-radius: 12px; padding: 14px; }
.controls { display: grid; grid-template-columns: minmax(320px, 1fr) auto auto auto; gap: 10px; align-items: center; }
input[type="search"] { font-size: 26px; padding: 14px; border-radius: 10px; border: 1px solid #bcaed0; }
button, .btn-link { font-size: 24px; border: 0; border-radius: 10px; padding: 14px 16px; cursor: pointer; text-decoration: none; display: inline-block; }
.primary { background: #5f2f86; color: #fff; }
.danger { background: #be2c22; color: #fff; }
.ghost { background: #ece7f3; color: #2b1d3c; }
#message { min-height: 30px; font-weight: 700; font-size: 22px; padding-top: 8px; }
.status-grid { display: grid; grid-template-columns: repeat(4, minmax(150px, 1fr)); gap: 10px; }
.status-card { background: #f4edf9; border-radius: 10px; padding: 12px; }
.status-label { font-size: 14px; color: #5e4b73; text-transform: uppercase; }
.status-value { font-size: 34px; font-weight: 700; }
table { width: 100%; border-collapse: collapse; }
th, td { border-bottom: 1px solid #e8e0ef; padding: 10px; text-align: left; font-size: 20px; }
#generalWrap { max-height: 420px; overflow: auto; border: 1px solid #e5dcec; border-radius: 10px; }
#generalWrap thead th { position: sticky; top: 0; background: #f8f3fb; z-index: 1; }
#eventLog { max-height: 260px; overflow: auto; background: #faf8fc; border-radius: 10px; border: 1px solid #ece4f1; }
#eventLog table { font-size: 16px; }
@media (max-width: 1360px) {
  .status-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
  .controls { grid-template-columns: 1fr 1fr; }
}
</style>
</head>
<body>
<header>
  <div class="header-row">
    <h1>Generell Oppbevaring Admin</h1>
    <div class="header-actions">
      <button id="cursorToggle" class="ghost" type="button">Musepeker: PÅ</button>
      <a class="btn-link ghost" href="index.php">Tilbake</a>
      <a class="btn-link ghost" href="admin.php">Mobilhotell admin</a>
    </div>
  </div>
</header>
<main>
  <section class="panel">
    <div class="controls">
      <input id="search" type="search" placeholder="Søk navn eller QR (generell oppbevaring)">
      <button id="refresh" class="ghost">Oppdater</button>
      <button id="auto" class="primary">Auto: PÅ</button>
      <a class="btn-link ghost" href="backup.php">Last ned backup</a>
    </div>
    <div id="message"></div>
  </section>

  <section class="panel">
    <h2>Status</h2>
    <div id="health" class="status-grid"></div>
  </section>

  <section class="panel">
    <h2>Aktive i generell oppbevaring</h2>
    <div id="generalWrap">
      <table>
        <thead><tr><th>Navn</th><th>QR</th><th>Innlevert</th><th>Handling</th></tr></thead>
        <tbody id="generalBody"></tbody>
      </table>
    </div>
  </section>

  <section class="panel">
    <h2>Hendelser (generell oppbevaring)</h2>
    <div id="eventLog"></div>
  </section>
</main>

<script>
(() => {
  const search = document.getElementById('search');
  const refresh = document.getElementById('refresh');
  const autoBtn = document.getElementById('auto');
  const health = document.getElementById('health');
  const generalBody = document.getElementById('generalBody');
  const eventLog = document.getElementById('eventLog');
  const message = document.getElementById('message');
  const cursorToggle = document.getElementById('cursorToggle');

  let autoOn = true;
  let poll = null;
  let searchTimer = null;

  function applyCursorSetting(hidden) {
    document.body.classList.toggle('cursor-hidden', hidden);
    cursorToggle.textContent = 'Musepeker: ' + (hidden ? 'AV' : 'PÅ');
  }

  function loadCursorSetting() {
    const hidden = localStorage.getItem('admin_cursor_hidden') === '1';
    applyCursorSetting(hidden);
  }

  function esc(s) {
    return String(s ?? '').replace(/[&<>"']/g, (m) => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m]));
  }

  async function api(url, opts) {
    const res = await fetch(url, opts);
    return await res.json();
  }

  function setMessage(text, ok) {
    message.textContent = text;
    message.style.color = ok ? '#0b8e48' : '#be2c22';
  }

  async function loadHealth() {
    const data = await api('admin_api.php?action=health');
    const s = data.summary || {};
    const roleNo = s.node_role === 'klient' ? 'Klient' : 'Hoved';
    const items = [
      ['Rolle', roleNo],
      ['Generell aktive', Number(s.active_storage_checkins || 0)],
      ['Mobilhotell aktive', Number(s.active_checkins || 0)],
      ['Server tid', s.server_time || '-'],
    ];

    health.innerHTML = items.map(([label, value]) =>
      '<div class="status-card"><div class="status-label">' + esc(label) + '</div><div class="status-value">' + esc(value) + '</div></div>'
    ).join('');
  }

  async function loadGeneralActive() {
    const q = search.value.trim();
    const data = await api('admin_api.php?action=storage_active_list&q=' + encodeURIComponent(q));
    const items = data.items || [];
    if (!items.length) {
      generalBody.innerHTML = '<tr><td colspan="4">Ingen aktive i generell oppbevaring</td></tr>';
      return;
    }

    generalBody.innerHTML = items.map((it) =>
      '<tr>'
      + '<td>' + esc(it.name) + '</td>'
      + '<td>' + esc(it.qr_code) + '</td>'
      + '<td>' + esc(it.checkin_time) + '</td>'
      + '<td><button class="danger" data-storage-out="' + Number(it.session_id) + '">Utlever</button></td>'
      + '</tr>'
    ).join('');
  }

  async function loadEvents() {
    const data = await api('admin_api.php?action=recent_events&limit=120');
    const allItems = data.items || [];
    const items = allItems.filter((it) => {
      const t = String(it.event_type || '');
      return t.startsWith('storage_') || t.startsWith('admin_storage_');
    });

    if (!items.length) {
      eventLog.innerHTML = '<div style="padding:8px;">Ingen hendelser for generell oppbevaring ennå</div>';
      return;
    }

    eventLog.innerHTML = '<table><thead><tr><th>Tid</th><th>Type</th><th>Melding</th><th>Data</th></tr></thead><tbody>'
      + items.map((it) =>
        '<tr>'
        + '<td>' + esc(it.created_at || '') + '</td>'
        + '<td>' + esc(it.event_type || '') + '</td>'
        + '<td>' + esc(it.message || '') + '</td>'
        + '<td>' + esc(it.metadata ? JSON.stringify(it.metadata) : '') + '</td>'
        + '</tr>'
      ).join('')
      + '</tbody></table>';
  }

  async function manualStorageCheckout(sessionId) {
    try {
      const data = await api('admin_api.php?action=storage_manual_checkout', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({session_id: sessionId})
      });
      if (!data.success) {
        setMessage('Utlevering feilet', false);
        return;
      }
      await loadAll(true);
    } catch {
      setMessage('Utlevering feilet', false);
    }
  }

  async function loadAll(forceMsg = false) {
    try {
      await Promise.all([loadHealth(), loadGeneralActive(), loadEvents()]);
      if (forceMsg) setMessage('Data oppdatert', true);
    } catch {
      setMessage('Kunne ikke hente data', false);
    }
  }

  generalBody.addEventListener('click', (e) => {
    const out = e.target.closest('[data-storage-out]');
    if (out) manualStorageCheckout(Number(out.dataset.storageOut));
  });

  search.addEventListener('input', () => {
    if (searchTimer) clearTimeout(searchTimer);
    searchTimer = setTimeout(loadGeneralActive, 120);
  });

  refresh.addEventListener('click', () => loadAll(true));

  autoBtn.addEventListener('click', () => {
    autoOn = !autoOn;
    autoBtn.textContent = 'Auto: ' + (autoOn ? 'PÅ' : 'AV');
    autoBtn.className = autoOn ? 'primary' : 'ghost';
    if (poll) clearInterval(poll);
    if (autoOn) poll = setInterval(() => loadAll(false), 15000);
  });

  cursorToggle.addEventListener('click', () => {
    const hidden = !document.body.classList.contains('cursor-hidden');
    localStorage.setItem('admin_cursor_hidden', hidden ? '1' : '0');
    applyCursorSetting(hidden);
  });

  poll = setInterval(() => loadAll(false), 15000);
  loadCursorSetting();
  loadAll(true);
})();
</script>
</body>
</html>
