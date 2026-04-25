<!doctype html>
<html lang="no">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Mobilhotell Admin</title>
<style>
body { margin: 0; font-family: Arial, sans-serif; background: #f4f6f4; color: #111; }
header { background: #055548; color: #fff; padding: 14px 18px; }
main { padding: 14px; display: grid; gap: 14px; }
.panel { background: #fff; border: 1px solid #d9dfda; border-radius: 10px; padding: 12px; }
.controls { display: grid; grid-template-columns: 1fr auto auto; gap: 8px; }
input[type="search"] { font-size: 20px; padding: 10px; border-radius: 8px; border: 1px solid #ccc; }
button { font-size: 16px; border: 0; border-radius: 8px; padding: 10px 12px; cursor: pointer; }
.primary { background: #056256; color: #fff; }
.warn { background: #e8c34b; }
.danger { background: #be2c22; color: #fff; }
.ghost { background: #ecefec; }
table { width: 100%; border-collapse: collapse; }
th, td { border-bottom: 1px solid #e4e8e4; padding: 8px; text-align: left; }
.grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(90px, 1fr)); gap: 8px; }
.slot { border-radius: 8px; min-height: 70px; color: #fff; font-weight: 700; border: 0; }
.slot.free { background: #0b8e48; }
.slot.busy { background: #c53326; }
.slot.disabled { background: #888; }
.slot small { display: block; font-size: 11px; opacity: .9; }
#message { min-height: 22px; font-weight: 700; }
</style>
</head>
<body>
<header><h1>Mobilhotell Admin</h1></header>
<main>
  <section class="panel">
    <div class="controls">
      <input id="search" type="search" placeholder="Søk navn eller QR">
      <button id="refresh" class="ghost">Oppdater</button>
      <button id="auto" class="primary">Auto: PÅ</button>
    </div>
    <div id="message"></div>
  </section>

  <section class="panel">
    <h2>Aktive innleveringer</h2>
    <table>
      <thead><tr><th>Navn</th><th>QR</th><th>Slot</th><th>Type</th><th>Tid</th><th>Handling</th></tr></thead>
      <tbody id="activeBody"></tbody>
    </table>
  </section>

  <section class="panel">
    <h2>Slots</h2>
    <h3>Oppbevaring</h3>
    <div id="gridStorage" class="grid"></div>
    <h3>Lading</h3>
    <div id="gridCharging" class="grid"></div>
    <div id="slotDetail"></div>
  </section>
</main>

<script>
(() => {
  const search = document.getElementById('search');
  const refresh = document.getElementById('refresh');
  const autoBtn = document.getElementById('auto');
  const activeBody = document.getElementById('activeBody');
  const gridStorage = document.getElementById('gridStorage');
  const gridCharging = document.getElementById('gridCharging');
  const slotDetail = document.getElementById('slotDetail');
  const message = document.getElementById('message');

  let autoOn = true;
  let poll = null;
  let searchTimer = null;

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
      + '<td>' + esc(it.slot_type) + '</td>'
      + '<td>' + esc(it.checkin_time) + '</td>'
      + '<td><button class="danger" data-out="' + Number(it.session_id) + '">Utlever</button> '
      + '<button class="warn" data-down="' + Number(it.slot_id) + '">Ute av drift</button></td>'
      + '</tr>'
    ).join('');
  }

  async function loadGrid() {
    const data = await api('admin_api.php?action=slot_grid');
    const items = data.items || [];

    const render = (slot) => '<button class="slot ' + esc(slot.status) + '" data-slot="' + esc(slot.slot_number) + '">'
      + esc(slot.slot_number) + '<small>' + esc(slot.name || 'ledig') + '</small></button>';

    gridStorage.innerHTML = items.filter(i => i.slot_type === 'storage').map(render).join('');
    gridCharging.innerHTML = items.filter(i => i.slot_type === 'charging').map(render).join('');
  }

  async function loadAll() {
    try {
      await Promise.all([loadActive(), loadGrid()]);
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
      await loadAll();
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
      await loadAll();
    } catch {
      setMessage('Kunne ikke oppdatere slot', false);
    }
  }

  async function showSlot(slotNumber) {
    try {
      const data = await api('admin_api.php?action=slot_detail&slot_number=' + encodeURIComponent(slotNumber));
      const s = data.slot;
      const active = Number(s.is_active) === 1;
      slotDetail.innerHTML = '<div style="margin-top:10px; padding:10px; background:#eef2ee; border-radius:8px">'
        + '<div><strong>Slot:</strong> ' + esc(s.slot_number) + '</div>'
        + '<div><strong>Status:</strong> ' + esc(s.status) + '</div>'
        + '<div><strong>Deltaker:</strong> ' + esc(s.name || 'Ingen') + '</div>'
        + '<div style="margin-top:8px">'
        + '<button class="' + (active ? 'warn' : 'primary') + '" data-toggle-slot="' + Number(s.slot_id) + '" data-next="' + (active ? 0 : 1) + '">' + (active ? 'Sett ute av drift' : 'Aktiver slot') + '</button>'
        + (s.session_id ? ' <button class="danger" data-out="' + Number(s.session_id) + '">Utlever fra slot</button>' : '')
        + '</div></div>';
    } catch {
      setMessage('Kunne ikke hente slotdetalj', false);
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

    const out = e.target.closest('#slotDetail [data-out]');
    if (out) manualCheckout(Number(out.dataset.out));
  });

  search.addEventListener('input', () => {
    if (searchTimer) clearTimeout(searchTimer);
    searchTimer = setTimeout(loadActive, 120);
  });

  refresh.addEventListener('click', loadAll);
  autoBtn.addEventListener('click', () => {
    autoOn = !autoOn;
    autoBtn.textContent = 'Auto: ' + (autoOn ? 'PÅ' : 'AV');
    autoBtn.className = autoOn ? 'primary' : 'ghost';
    if (poll) clearInterval(poll);
    if (autoOn) poll = setInterval(loadAll, 7000);
  });

  poll = setInterval(loadAll, 7000);
  loadAll();
})();
</script>
</body>
</html>
