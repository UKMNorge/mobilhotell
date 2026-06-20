<!doctype html>
<html lang="no">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Mobilhotell Admin</title>
<style>
body { margin: 0; font-family: Arial, sans-serif; background: #e9efea; color: #111; }
body.cursor-hidden, body.cursor-hidden * { cursor: none !important; }
header { background: #055548; color: #fff; padding: 14px 22px; position: sticky; top: 0; z-index: 10; }
.header-row { max-width: 1800px; margin: 0 auto; display: flex; align-items: center; justify-content: space-between; gap: 12px; }
.header-row h1 { margin: 0; font-size: 40px; }
.header-actions { display: flex; gap: 10px; flex-wrap: wrap; }
main { max-width: 1800px; margin: 0 auto; padding: 18px 20px 24px; display: grid; gap: 16px; }
.panel { background: #fff; border: 1px solid #d9dfda; border-radius: 12px; padding: 14px; }
.panel[hidden] { display: none; }
.panel h2 { margin: 0 0 10px; font-size: 34px; }
.panel h3 { margin: 10px 0 8px; font-size: 26px; }
.controls { display: grid; grid-template-columns: minmax(320px, 1fr) auto auto auto; gap: 10px; align-items: center; }
input[type="search"], input[type="file"] { font-size: 26px; padding: 14px; border-radius: 10px; border: 1px solid #bcc7c0; }
button, .btn-link { font-size: 24px; border: 0; border-radius: 10px; padding: 14px 16px; cursor: pointer; text-decoration: none; display: inline-block; }
.primary { background: #056256; color: #fff; }
.warn { background: #e8c34b; }
.danger { background: #be2c22; color: #fff; }
.ghost { background: #ecefec; }
.status-grid { display: grid; grid-template-columns: repeat(6, minmax(150px, 1fr)); gap: 10px; }
.status-card { background: #eef2ee; border-radius: 10px; padding: 12px; }
.status-label { font-size: 14px; color: #4f5a54; text-transform: uppercase; }
.status-value { font-size: 34px; font-weight: 700; }
table { width: 100%; border-collapse: collapse; }
th, td { border-bottom: 1px solid #e4e8e4; padding: 10px; text-align: left; font-size: 20px; }
#activeWrap { max-height: 320px; overflow: auto; border: 1px solid #dfe7df; border-radius: 10px; }
#activeWrap table { min-width: 100%; }
#activeWrap thead th { position: sticky; top: 0; background: #f7faf7; z-index: 1; }
.grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(120px, 1fr)); gap: 8px; }
.slot { border-radius: 10px; min-height: 90px; color: #fff; font-weight: 700; border: 0; font-size: 20px; }
.slot.free { background: #0b8e48; }
.slot.busy { background: #c53326; }
.slot.disabled { background: #888; }
.slot small { display: block; font-size: 13px; opacity: .9; }
#message { min-height: 30px; font-weight: 700; font-size: 22px; padding-top: 8px; }
#eventLog { max-height: 320px; overflow: auto; background: #f8faf8; border-radius: 10px; border: 1px solid #e3e8e3; }
#eventLog table { font-size: 16px; }
.inline-form { display: grid; grid-template-columns: 1fr auto; gap: 10px; }
.view-nav { display: grid; grid-template-columns: repeat(6, minmax(0, 1fr)); gap: 10px; margin-top: 10px; }
.view-toggle { background: #ecefec; color: #17332f; font-weight: 700; }
.view-toggle.active { background: #056256; color: #fff; }
#screentimeWrap { max-height: 460px; overflow: auto; border: 1px solid #dfe7df; border-radius: 10px; }
#screentimeWrap table { min-width: 100%; }
#screentimeWrap thead th { position: sticky; top: 0; background: #f7faf7; z-index: 1; }
.screentime-tools {
  display: flex;
  gap: 10px;
  align-items: center;
  flex-wrap: wrap;
  margin-bottom: 10px;
}
.screentime-tools select {
  font-size: 22px;
  padding: 10px 12px;
  border-radius: 10px;
  border: 1px solid #bcc7c0;
  background: #fff;
}
.screentime-tools label {
  font-size: 22px;
  display: inline-flex;
  align-items: center;
  gap: 8px;
}
.screentime-tools input[type="checkbox"] {
  width: 26px;
  height: 26px;
}
.detox-box {
  margin-top: 12px;
  background: #f4f8f5;
  border: 1px solid #dbe5de;
  border-radius: 10px;
  padding: 12px;
}
.detox-box p {
  margin: 0 0 10px;
  font-size: 20px;
  line-height: 1.35;
}
.detox-tools {
  display: flex;
  gap: 10px;
  flex-wrap: wrap;
  align-items: center;
  margin-bottom: 10px;
}
.detox-tools input[type="date"] {
  font-size: 22px;
  padding: 10px 12px;
  border-radius: 10px;
  border: 1px solid #bcc7c0;
}
.slots2-wrap {
  display: grid;
  gap: 16px;
}
.slots2-map {
  background: #f5f8f6;
  border: 1px solid #dbe5de;
  border-radius: 12px;
  padding: 12px;
}
.slots2-map h3 {
  margin: 0 0 10px;
}
.slots2-board {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 8px;
}
.slots2-segment {
  background: #0f1413;
  border: 2px solid #d4c6b6;
  border-radius: 8px;
  padding: 8px;
}
.slots2-segment-title {
  color: #e9ece8;
  font-size: 15px;
  margin-bottom: 8px;
  text-align: center;
  font-weight: 700;
}
.slots2-grid {
  display: grid;
  grid-template-columns: repeat(20, minmax(0, 1fr));
  gap: 5px;
}
.slots2-cell {
  border: 0;
  border-radius: 6px;
  padding: 4px 0;
  min-height: 28px;
  font-size: 10px;
  font-weight: 700;
  color: #fff;
  background: #46524c;
}
.slots2-cell.free {
  background: #0b8e48;
}
.slots2-cell.busy {
  background: #c53326;
}
.slots2-cell.disabled {
  background: #8f8f8f;
}
.slots2-legend {
  display: flex;
  gap: 12px;
  flex-wrap: wrap;
  margin-top: 10px;
  font-size: 15px;
}
.slots2-legend span {
  display: inline-flex;
  align-items: center;
  gap: 6px;
}
.slots2-dot {
  width: 12px;
  height: 12px;
  border-radius: 999px;
  display: inline-block;
}
#detoxWrap {
  max-height: 300px;
  overflow: auto;
  border: 1px solid #dfe7df;
  border-radius: 10px;
  background: #fff;
}
#detoxWrap table {
  min-width: 100%;
}
#detoxWrap thead th {
  position: sticky;
  top: 0;
  background: #f7faf7;
  z-index: 1;
}
.modal {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.45);
  display: none;
  align-items: center;
  justify-content: center;
  z-index: 50;
  padding: 20px;
}
.modal.open {
  display: flex;
}
.modal-card {
  width: min(760px, 100%);
  background: #fff;
  border: 1px solid #d9dfda;
  border-radius: 14px;
  padding: 16px;
  box-shadow: 0 18px 50px rgba(0, 0, 0, 0.25);
}
.modal-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  margin-bottom: 10px;
}
.modal-head h3 {
  margin: 0;
  font-size: 30px;
}
#slotModalBody {
  font-size: 24px;
}
@media (max-width: 1360px) {
  .status-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); }
  .controls { grid-template-columns: 1fr 1fr; }
  .view-nav { grid-template-columns: repeat(2, minmax(0, 1fr)); }
  .slots2-board { grid-template-columns: 1fr; }
  .slots2-grid { grid-template-columns: repeat(10, minmax(0, 1fr)); }
}
</style>
</head>
<body>
<header>
  <div class="header-row">
    <h1>Mobilhotell Admin</h1>
    <div class="header-actions">
      <button id="cursorToggle" class="ghost" type="button">Musepeker: PÅ</button>
      <a class="btn-link ghost" href="index.php">Tilbake</a>
    </div>
  </div>
</header>
<main>
  <section class="panel">
    <div class="controls">
      <input id="search" type="search" placeholder="Søk navn eller QR">
      <button id="refresh" class="ghost">Oppdater</button>
      <button id="auto" class="primary">Auto: PÅ</button>
      <a class="btn-link ghost" href="backup.php">Last ned backup</a>
    </div>
    <div class="view-nav">
      <button type="button" class="view-toggle active" data-view="health">Driftstatus</button>
      <button type="button" class="view-toggle" data-view="detox">Digital Detox</button>
      <button type="button" class="view-toggle" data-view="active">Aktive innleveringer</button>
      <button type="button" class="view-toggle" data-view="screentime">Skjermtid</button>
      <button type="button" class="view-toggle" data-view="slots">Slots</button>
      <button type="button" class="view-toggle" data-view="import">Importer</button>
    </div>
    <div id="message"></div>
  </section>

  <section id="panelHealth" class="panel">
    <h2>Driftstatus</h2>
    <div id="health" class="status-grid"></div>
  </section>

  <section id="panelDetox" class="panel" hidden>
    <div class="detox-box" style="margin-top:0;">
      <h2>Digital Detox</h2>
      <p><strong>Digital Detox - tør du legge vekk mobilen?</strong></p>
      <p>Under festivalen kan du prøve <strong>Digital Detox</strong>. Lever mobilen din i sekretariatet ved Retrohallen før kl. 09:30, og utfordre deg selv til en dag uten skjerm.</p>
      <p>Hvis mobilen fortsatt ligger der kl. 18:30, er du invitert til festivalsjefens <strong>DD-møte (Digital Detox)</strong> i kaffebaren. Her får du en liten belønning for innsatsen, noe godt å kose deg med etter middagen, og mulighet til å dele erfaringer med andre som har vært skjermfrie hele dagen.</p>
      <p><strong>Digital Detox gjennomføres hver dag under festivalen - søndag, mandag og tirsdag.</strong> Ingen påmelding, bare lever inn mobilen og prøv.</p>

      <div class="detox-tools">
        <label for="detoxDay">Dag</label>
        <input id="detoxDay" type="date">
        <button id="loadDetox" class="primary" type="button">Vis hvem som har klart</button>
        <button id="printDetox" class="warn" type="button">Skriv ut liste</button>
      </div>
      <div id="detoxMeta" style="font-size:18px; margin-bottom:8px; color:#29433c;"></div>
      <div id="detoxWrap">
        <table>
          <thead><tr><th>Navn</th><th>QR</th><th>Innlevert</th><th>Utlevert</th></tr></thead>
          <tbody id="detoxBody"><tr><td colspan="4">Velg dag og trykk "Vis hvem som har klart"</td></tr></tbody>
        </table>
      </div>
    </div>
  </section>

  <section id="panelEvents" class="panel">
    <h2>Hendelseslogg</h2>
    <div id="eventLog"></div>
  </section>

  <section id="panelActive" class="panel" hidden>
    <h2>Aktive innleveringer</h2>
    <div id="activeWrap">
      <table>
        <thead><tr><th>Navn</th><th>QR</th><th>Slot</th><th>Type</th><th>Tid</th><th>Handling</th></tr></thead>
        <tbody id="activeBody"></tbody>
      </table>
    </div>
  </section>

  <section id="panelScreentime" class="panel" hidden>
    <h2>Skjermtid Oversikt</h2>
    <div class="screentime-tools">
      <label for="screentimeSort">Sorter</label>
      <select id="screentimeSort">
        <option value="time_desc">Mest skjermfri tid</option>
        <option value="name_asc">Navn A-Å</option>
      </select>
      <label><input id="screentimeOnlyCheckedIn" type="checkbox"> Kun innleverte</label>
      <button id="clearScreentime" class="danger" type="button">Tøm skjermtidlogg</button>
    </div>
    <div id="screentimeWrap">
      <table>
        <thead><tr><th>Navn</th><th>QR</th><th>Type</th><th>Status</th><th>Skjermfri tid</th></tr></thead>
        <tbody id="screentimeBody"></tbody>
      </table>
    </div>
  </section>

  <section id="panelSlots" class="panel" hidden>
    <h2>Slots - Visuell Fyllingsoversikt</h2>
    <div class="slots2-wrap">
      <div class="slots2-map">
        <h3>Oppbevaring (O001-O180)</h3>
        <div class="slots2-board">
          <div class="slots2-segment">
            <div class="slots2-segment-title">1-60</div>
            <div id="slots2Storage1" class="slots2-grid"></div>
          </div>
          <div class="slots2-segment">
            <div class="slots2-segment-title">61-120</div>
            <div id="slots2Storage2" class="slots2-grid"></div>
          </div>
          <div class="slots2-segment">
            <div class="slots2-segment-title">121-180</div>
            <div id="slots2Storage3" class="slots2-grid"></div>
          </div>
        </div>
      </div>

      <div class="slots2-map">
        <h3>Lading</h3>
        <div class="slots2-board">
          <div class="slots2-segment">
            <div class="slots2-segment-title">Lading USB-A (1-60)</div>
            <div id="slots2Charging1" class="slots2-grid"></div>
          </div>
          <div class="slots2-segment">
            <div class="slots2-segment-title">Lading USB-C (61-120)</div>
            <div id="slots2Charging2" class="slots2-grid"></div>
          </div>
          <div class="slots2-segment">
            <div class="slots2-segment-title">Lading USB-C (121-180)</div>
            <div id="slots2Charging3" class="slots2-grid"></div>
          </div>
        </div>
        <div class="slots2-legend">
          <span><i class="slots2-dot" style="background:#0b8e48"></i> Ledig</span>
          <span><i class="slots2-dot" style="background:#c53326"></i> Opptatt</span>
          <span><i class="slots2-dot" style="background:#8f8f8f"></i> Ute av drift</span>
        </div>
      </div>
    </div>
  </section>

  <section id="panelImport" class="panel" hidden>
    <h2>Importer Deltakere (CSV)</h2>
    <form id="importForm" class="inline-form">
      <input id="importFile" type="file" accept=".csv,text/csv">
      <button type="submit" class="primary">Importer</button>
    </form>
    <div style="font-size:13px; margin-top:8px; color:#4a5450;">Kolonner: qr_code, first_name, last_name, phone_number, county, participant_type, image_path</div>
    <div style="margin-top:8px;"><a class="btn-link ghost" href="sql/sample_participants.csv" download>Last ned eksempel-CSV</a></div>
  </section>
</main>

<div id="slotModal" class="modal" aria-hidden="true">
  <div class="modal-card" role="dialog" aria-modal="true" aria-labelledby="slotModalTitle">
    <div class="modal-head">
      <h3 id="slotModalTitle">Slotdetaljer</h3>
      <button id="slotModalClose" type="button" class="ghost">Lukk</button>
    </div>
    <div id="slotModalBody"></div>
  </div>
</div>

<script>
(() => {
  const search = document.getElementById('search');
  const refresh = document.getElementById('refresh');
  const autoBtn = document.getElementById('auto');
  const activeBody = document.getElementById('activeBody');
  const slots2Storage1 = document.getElementById('slots2Storage1');
  const slots2Storage2 = document.getElementById('slots2Storage2');
  const slots2Storage3 = document.getElementById('slots2Storage3');
  const slots2Charging1 = document.getElementById('slots2Charging1');
  const slots2Charging2 = document.getElementById('slots2Charging2');
  const slots2Charging3 = document.getElementById('slots2Charging3');
  const message = document.getElementById('message');
  const health = document.getElementById('health');
  const eventLog = document.getElementById('eventLog');
  const detoxDay = document.getElementById('detoxDay');
  const loadDetox = document.getElementById('loadDetox');
  const printDetox = document.getElementById('printDetox');
  const detoxMeta = document.getElementById('detoxMeta');
  const detoxBody = document.getElementById('detoxBody');
  const screentimeBody = document.getElementById('screentimeBody');
  const screentimeSort = document.getElementById('screentimeSort');
  const screentimeOnlyCheckedIn = document.getElementById('screentimeOnlyCheckedIn');
  const clearScreentime = document.getElementById('clearScreentime');
  const importForm = document.getElementById('importForm');
  const importFile = document.getElementById('importFile');
  const slotModal = document.getElementById('slotModal');
  const slotModalBody = document.getElementById('slotModalBody');
  const slotModalClose = document.getElementById('slotModalClose');
  const cursorToggle = document.getElementById('cursorToggle');
  const viewButtons = Array.from(document.querySelectorAll('[data-view]'));
  const panels = {
    health: document.getElementById('panelHealth'),
    detox: document.getElementById('panelDetox'),
    active: document.getElementById('panelActive'),
    screentime: document.getElementById('panelScreentime'),
    slots: document.getElementById('panelSlots'),
    import: document.getElementById('panelImport')
  };
  const panelEvents = document.getElementById('panelEvents');

  let autoOn = true;
  let poll = null;
  let searchTimer = null;
  const idleMs = 30000;
  let idleTimer = null;
  let screentimeItems = [];
  let currentView = 'health';
  let lastScreentimeLoadAt = 0;
  let lastDetoxLoadAt = 0;

  function todayIso() {
    const d = new Date();
    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return y + '-' + m + '-' + day;
  }

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

  async function loadHealth() {
    const data = await api('admin_api.php?action=health');
    const s = data.summary || {};
    const roleNo = s.node_role === 'klient' ? 'Klient' : 'Hoved';
    const items = [
      ['Rolle', roleNo],
      ['Aktive', s.active_checkins || 0],
      ['Generell aktive', s.active_storage_checkins || 0],
      ['Slots totalt', s.slots_total || 0],
      ['Slots ledige', s.slots_free_active || 0],
      ['Slots opptatt', s.slots_busy || 0],
      ['Deltakere', s.participants_total || 0],
      ['IP', s.ip_address || '-'],
      ['WiFi', s.wifi_ssid || '-'],
      ['Server tid', s.server_time || '-']
    ];

    health.innerHTML = items.map(([label, value]) =>
      '<div class="status-card"><div class="status-label">' + esc(label) + '</div><div class="status-value">' + esc(value) + '</div></div>'
    ).join('');
  }

  async function loadEvents() {
    const data = await api('admin_api.php?action=recent_events&limit=80');
    const items = data.items || [];
    if (!items.length) {
      eventLog.innerHTML = '<div style="padding:8px;">Ingen hendelser ennå</div>';
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

  function renderDetox(items) {
    if (!items.length) {
      detoxBody.innerHTML = '<tr><td colspan="4">Ingen deltakere har klart Digital Detox for valgt dag enda.</td></tr>';
      return;
    }

    detoxBody.innerHTML = items.map((it) =>
      '<tr>'
      + '<td>' + esc(it.name || '') + '</td>'
      + '<td>' + esc(it.qr_code || '') + '</td>'
      + '<td>' + esc(it.first_checkin || '-') + '</td>'
      + '<td>' + esc(it.checkout_time || 'Fortsatt innlevert') + '</td>'
      + '</tr>'
    ).join('');
  }

  async function loadDigitalDetox(force = false) {
    const now = Date.now();
    if (!force && (now - lastDetoxLoadAt) < 15000) {
      return;
    }

    const day = detoxDay.value || todayIso();
    const data = await api('admin_api.php?action=digital_detox_report&day=' + encodeURIComponent(day));
    if (!data.success) {
      detoxMeta.textContent = 'Kunne ikke hente Digital Detox-liste';
      detoxBody.innerHTML = '<tr><td colspan="4">Feil ved henting</td></tr>';
      return;
    }

    const count = Number(data.count || 0);
    const doneText = data.day_complete ? 'Dagen er ferdig evaluert.' : 'Dagen er ikke ferdig evaluert enda (for 18:30).';
    detoxMeta.textContent = data.day + ' - ' + count + ' klarte Digital Detox. ' + doneText;
    renderDetox(data.items || []);
    lastDetoxLoadAt = now;
  }

  async function printDigitalDetoxList() {
    const day = detoxDay.value || todayIso();
    try {
      const data = await api('admin_api.php?action=digital_detox_print', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({day})
      });
      if (!data.success) {
        setMessage('Utskrift feilet for Digital Detox', false);
        return;
      }
      setMessage('Digital Detox-liste sendt til kvitteringsskriver', true);
    } catch {
      setMessage('Utskrift feilet for Digital Detox', false);
    }
  }

  function setMessage(text, ok) {
    message.textContent = text;
    message.style.color = ok ? '#0b8e48' : '#be2c22';
  }

  function restartIdleTimer() {
    if (idleTimer) clearTimeout(idleTimer);
    idleTimer = setTimeout(() => {
      window.location.href = 'index.php';
    }, idleMs);
  }

  function statusNo(status) {
    if (status === 'free') return 'Ledig';
    if (status === 'busy') return 'Opptatt';
    if (status === 'disabled') return 'Ute av drift';
    return status || '-';
  }

  function typeNo(type, slotNumber) {
    const slot = String(slotNumber || '').toUpperCase();
    if (slot.startsWith('O')) return 'Oppbevaring';
    if (slot.startsWith('A')) return 'Lading USB-A';
    if (slot.startsWith('C')) return 'Lading USB-C';
    if (type === 'storage') return 'Oppbevaring';
    if (type === 'charging') return 'Lading';
    return type || '-';
  }

  function formatDuration(totalSeconds) {
    const sec = Math.max(0, Number(totalSeconds || 0));
    const h = Math.floor(sec / 3600);
    const m = Math.floor((sec % 3600) / 60);
    return h + ' t ' + m + ' min';
  }

  function renderScreentimeTable() {
    let items = [...screentimeItems];

    if (screentimeOnlyCheckedIn.checked) {
      items = items.filter((it) => !!it.checked_in);
    }

    if (screentimeSort.value === 'name_asc') {
      items.sort((a, b) => String(a.name || '').localeCompare(String(b.name || ''), 'no'));
    } else {
      items.sort((a, b) => Number(b.screenfree_seconds || 0) - Number(a.screenfree_seconds || 0));
    }

    if (!items.length) {
      screentimeBody.innerHTML = '<tr><td colspan="5">Ingen deltakere</td></tr>';
      return;
    }

    screentimeBody.innerHTML = items.map((it) => {
      const status = it.checked_in ? 'Innlevert' : 'Ikke innlevert';
      return '<tr>'
        + '<td>' + esc(it.name) + '</td>'
        + '<td>' + esc(it.qr_code) + '</td>'
        + '<td>' + esc(it.participant_type || '-') + '</td>'
        + '<td>' + esc(status) + '</td>'
        + '<td>' + esc(formatDuration(it.screenfree_seconds)) + '</td>'
        + '</tr>';
    }).join('');
  }

  function openSlotModal(html) {
    slotModalBody.innerHTML = html;
    slotModal.classList.add('open');
    slotModal.setAttribute('aria-hidden', 'false');
  }

  function showView(viewName) {
    currentView = viewName;
    Object.entries(panels).forEach(([key, el]) => {
      el.hidden = key !== viewName;
    });
    panelEvents.hidden = viewName !== 'health';
    viewButtons.forEach((btn) => {
      btn.classList.toggle('active', btn.dataset.view === viewName);
    });
    if (viewName === 'active') {
      search.focus();
      loadActive();
    }
    if (viewName === 'slots') {
      loadGrid2();
    }
    if (viewName === 'screentime') {
      loadScreentime(true);
    }
    if (viewName === 'detox') {
      loadDigitalDetox(true);
    }
  }

  function closeSlotModal() {
    slotModal.classList.remove('open');
    slotModal.setAttribute('aria-hidden', 'true');
    slotModalBody.innerHTML = '';
  }

  async function loadActive() {
    const q = search.value.trim();
    const data = await api('admin_api.php?action=active_list&q=' + encodeURIComponent(q));
    const items = data.items || [];
    if (!items.length) {
      activeBody.innerHTML = '<tr><td colspan="6">Ingen aktive innleveringer</td></tr>';
      return;
    }

    activeBody.innerHTML = items.map((it) =>
      '<tr>'
      + '<td>' + esc(it.name) + '</td>'
      + '<td>' + esc(it.qr_code) + '</td>'
      + '<td>' + esc(it.slot_number) + '</td>'
      + '<td>' + esc(typeNo(it.slot_type, it.slot_number)) + '</td>'
      + '<td>' + esc(it.checkin_time) + '</td>'
      + '<td><button class="danger" data-out="' + Number(it.session_id) + '">Utlever</button> '
      + '<button class="warn" data-down="' + Number(it.slot_id) + '">Ute av drift</button></td>'
      + '</tr>'
    ).join('');
  }

  async function loadScreentime(force = false) {
    const now = Date.now();
    if (!force && (now - lastScreentimeLoadAt) < 60000) {
      return;
    }
    const data = await api('admin_api.php?action=screentime_overview&limit=1000');
    screentimeItems = data.items || [];
    lastScreentimeLoadAt = now;
    renderScreentimeTable();
  }

  function normalizeSlot(slot) {
    return String(slot || '').toUpperCase().replace(/[^A-Z0-9]/g, '');
  }

  function slotCode(prefix, number) {
    return prefix + String(number).padStart(3, '0');
  }

  function renderSlots2Segment(container, items, type, from, to) {
    const bySlot = new Map();
    items.forEach((it) => {
      bySlot.set(normalizeSlot(it.slot_number), it);
    });

    const cells = [];
    for (let n = from; n <= to; n++) {
      let code = '';
      if (type === 'storage') {
        code = slotCode('O', n);
      } else if (n <= 60) {
        code = slotCode('A', n);
      } else {
        code = slotCode('C', n - 60);
      }

      const it = bySlot.get(code);
      const status = it ? String(it.status || 'disabled') : 'disabled';
      cells.push('<button type="button" class="slots2-cell ' + esc(status) + '" title="' + esc(code) + '">' + n + '</button>');
    }

    container.innerHTML = cells.join('');
  }

  async function loadGrid2() {
    const data = await api('admin_api.php?action=slot_grid');
    const items = data.items || [];

    renderSlots2Segment(slots2Storage1, items, 'storage', 1, 60);
    renderSlots2Segment(slots2Storage2, items, 'storage', 61, 120);
    renderSlots2Segment(slots2Storage3, items, 'storage', 121, 180);
    renderSlots2Segment(slots2Charging1, items, 'charging', 1, 60);
    renderSlots2Segment(slots2Charging2, items, 'charging', 61, 120);
    renderSlots2Segment(slots2Charging3, items, 'charging', 121, 180);
  }

  async function loadAll(forceAll = false) {
    try {
      const tasks = [loadHealth(), loadEvents()];
      if (forceAll || currentView === 'detox') tasks.push(loadDigitalDetox(forceAll));
      if (forceAll || currentView === 'active') tasks.push(loadActive());
      if (forceAll || currentView === 'slots') tasks.push(loadGrid2());
      if (forceAll || currentView === 'screentime') tasks.push(loadScreentime(forceAll));
      await Promise.all(tasks);
      setMessage('Data oppdatert', true);
    } catch {
      setMessage('Kunne ikke hente data', false);
    }
  }

  async function manualCheckout(sessionId) {
    try {
      await api('admin_api.php?action=manual_checkout', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({session_id: sessionId})
      });
      await loadAll(true);
    } catch {
      setMessage('Utlevering feilet', false);
    }
  }

  async function setSlotActive(slotId, isActive) {
    try {
      await api('admin_api.php?action=set_slot_active', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({slot_id: slotId, is_active: isActive})
      });
      await loadAll(true);
    } catch {
      setMessage('Kunne ikke oppdatere slot', false);
    }
  }

  async function showSlot(slotNumber) {
    try {
      const data = await api('admin_api.php?action=slot_detail&slot_number=' + encodeURIComponent(slotNumber));
      const s = data.slot;
      const active = Number(s.is_active) === 1;
      const html = '<div style="padding:10px; background:#eef2ee; border-radius:8px">'
        + '<div><strong>Slot:</strong> ' + esc(s.slot_number) + '</div>'
        + '<div><strong>Type:</strong> ' + esc(typeNo(s.slot_type, s.slot_number)) + '</div>'
        + '<div><strong>Status:</strong> ' + esc(statusNo(s.status)) + '</div>'
        + '<div><strong>Deltaker:</strong> ' + esc(s.name || 'Ingen') + '</div>'
        + '<div style="margin-top:8px">'
        + '<button class="' + (active ? 'warn' : 'primary') + '" data-toggle-slot="' + Number(s.slot_id) + '" data-next="' + (active ? 0 : 1) + '">' + (active ? 'Sett ute av drift' : 'Aktiver slot') + '</button>'
        + (s.session_id ? ' <button class="danger" data-out="' + Number(s.session_id) + '">Utlever fra slot</button>' : '')
        + '</div></div>';
      openSlotModal(html);
    } catch {
      setMessage('Kunne ikke hente slotdetalj', false);
    }
  }

  async function clearScreentimeLog() {
    const ok = window.confirm('Tømme skjermtidlogg? Dette sletter historiske (utleverte) sesjoner.');
    if (!ok) return;

    try {
      const data = await api('admin_api.php?action=clear_screentime_log', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({})
      });
      if (!data.success) {
        setMessage('Kunne ikke tømme skjermtidlogg', false);
        return;
      }

      setMessage(
        'Skjermtidlogg tømt ('
        + Number(data.deleted_sessions || 0)
        + ' historiske slettet, '
        + Number(data.reset_active_sessions || 0)
        + ' aktive nullstilt)',
        true
      );
      await loadScreentime(true);
      await loadHealth();
      await loadEvents();
    } catch {
      setMessage('Kunne ikke tømme skjermtidlogg', false);
    }
  }

  activeBody.addEventListener('click', (e) => {
    const out = e.target.closest('[data-out]');
    if (out) manualCheckout(Number(out.dataset.out));

    const down = e.target.closest('[data-down]');
    if (down) setSlotActive(Number(down.dataset.down), 0);
  });

  document.body.addEventListener('click', (e) => {
    const slot = e.target.closest('[data-slot]');
    if (slot) showSlot(slot.dataset.slot);

    const t = e.target.closest('[data-toggle-slot]');
    if (t) setSlotActive(Number(t.dataset.toggleSlot), Number(t.dataset.next));

    const out = e.target.closest('#slotModal [data-out]');
    if (out) manualCheckout(Number(out.dataset.out));
  });

  slotModalClose.addEventListener('click', closeSlotModal);
  slotModal.addEventListener('click', (e) => {
    if (e.target === slotModal) closeSlotModal();
  });
  window.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') closeSlotModal();
  });

  search.addEventListener('input', () => {
    if (currentView !== 'active') return;
    if (searchTimer) clearTimeout(searchTimer);
    searchTimer = setTimeout(loadActive, 120);
  });

  refresh.addEventListener('click', () => loadAll(true));
  importForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    const file = importFile.files && importFile.files[0];
    if (!file) {
      setMessage('Velg en CSV-fil først', false);
      return;
    }

    const fd = new FormData();
    fd.append('file', file);

    try {
      const data = await api('admin_api.php?action=import_csv', {
        method: 'POST',
        body: fd
      });
      if (!data.success) {
        setMessage('Import feilet: ' + (data.error || 'ukjent'), false);
        return;
      }
      setMessage('Import ok: ' + Number(data.inserted || 0) + ' nye, ' + Number(data.updated || 0) + ' oppdatert', true);
      importForm.reset();
      await loadAll(true);
    } catch {
      setMessage('Import feilet', false);
    }
  });

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

  viewButtons.forEach((btn) => {
    btn.addEventListener('click', () => showView(btn.dataset.view));
  });

  screentimeSort.addEventListener('change', renderScreentimeTable);
  screentimeOnlyCheckedIn.addEventListener('change', renderScreentimeTable);
  clearScreentime.addEventListener('click', clearScreentimeLog);
  loadDetox.addEventListener('click', () => loadDigitalDetox(true));
  printDetox.addEventListener('click', printDigitalDetoxList);
  detoxDay.addEventListener('change', () => loadDigitalDetox(true));

  ['pointerdown', 'keydown', 'touchstart', 'scroll', 'mousemove'].forEach((evt) => {
    window.addEventListener(evt, restartIdleTimer, { passive: true });
  });

  detoxDay.value = todayIso();
  poll = setInterval(() => loadAll(false), 15000);
  loadCursorSetting();
  restartIdleTimer();
  showView('health');
  loadAll(true);
})();
</script>
</body>
</html>
