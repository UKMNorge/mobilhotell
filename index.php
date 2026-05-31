<!doctype html>
<html lang="no">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Mobilhotell</title>
<style>
body {
  margin: 0;
  font-family: Arial, sans-serif;
  background: #17332f;
  color: #fff;
  text-align: center;
  touch-action: manipulation;
  -webkit-tap-highlight-color: transparent;
  min-height: 100dvh;
  overflow: hidden;
  cursor: none;
}
body.mode-phone {
  background: #17332f;
}
body.mode-storage {
  background: #3a1f44;
}
body * {
  cursor: none !important;
}
main {
  max-width: 1320px;
  margin: 0 auto;
  padding: 16px 20px 22px;
  height: 100dvh;
  display: flex;
  flex-direction: column;
  box-sizing: border-box;
}
h1 {
  margin: 8px 0 2px;
  font-size: clamp(42px, 4.2vw, 58px);
}
.top-tools {
  display: grid;
  grid-template-columns: repeat(4, minmax(140px, 1fr));
  gap: 10px;
  margin-bottom: 12px;
  padding: 10px;
  border-radius: 12px;
  background: rgba(16, 36, 33, 0.94);
  backdrop-filter: blur(2px);
}
.mode-switch {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 10px;
  margin: 0 auto 10px;
  max-width: 1200px;
  position: sticky;
  top: 0;
  z-index: 20;
  padding: 10px;
  border-radius: 12px;
  background: rgba(9, 22, 20, 0.88);
  backdrop-filter: blur(2px);
}
.mode-btn {
  border: 2px solid rgba(255,255,255,.3);
  border-radius: 14px;
  background: #e7efe9;
  color: #16332f;
  font-size: clamp(26px, 2.1vw, 36px);
  font-weight: 800;
  padding: 18px;
  min-height: 82px;
}
.mode-btn.active {
  color: #fff;
  border-color: rgba(255,255,255,.6);
}
.mode-btn.mode-phone.active {
  background: #0d8f4a;
}
.mode-btn.mode-storage.active {
  background: #8b2fc8;
}
.chip {
  border: 0;
  border-radius: 12px;
  padding: 16px 16px;
  font-size: clamp(18px, 1.6vw, 24px);
  font-weight: 700;
  min-height: 62px;
  background: #ecf1ed;
  color: #17332f;
  display: flex;
  align-items: center;
  justify-content: center;
}
.chip.offline {
  background: #be2c22;
  color: #fff;
}
.chip-link {
  text-decoration: none;
}
.admin-panel {
  margin: 8px auto 12px;
  max-width: 1200px;
  text-align: left;
  background: rgba(11, 33, 30, 0.95);
  border: 1px solid rgba(255,255,255,.22);
  border-radius: 14px;
  padding: 14px;
}
body.mode-storage .top-tools {
  background: rgba(46, 21, 54, 0.94);
}
body.mode-storage .admin-panel {
  background: rgba(53, 25, 64, 0.94);
  border: 1px solid rgba(255,255,255,.3);
}
body.mode-storage .capacity-fill {
  background: #c45ef8;
}
.admin-panel[hidden] {
  display: none;
}
body.keyboard-open .admin-panel {
  display: none;
}
.admin-panel-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  margin-bottom: 10px;
}
.admin-panel h2 {
  margin: 0;
  font-size: 28px;
}
.admin-link {
  display: inline-block;
  border-radius: 10px;
  padding: 10px 14px;
  background: #e8efea;
  color: #17332f;
  font-weight: 700;
  text-decoration: none;
}
.capacity-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 10px;
}
.capacity-card {
  background: rgba(255,255,255,.1);
  border-radius: 12px;
  padding: 10px;
}
.capacity-label {
  font-size: 19px;
  font-weight: 700;
}
.capacity-meta {
  font-size: 22px;
  margin: 6px 0;
}
.capacity-track {
  height: 14px;
  border-radius: 999px;
  background: rgba(255,255,255,.2);
  overflow: hidden;
}
.capacity-fill {
  height: 100%;
  background: #2bd074;
  width: 0;
}
.capacity-fill.warn {
  background: #f2b936;
}
.capacity-fill.danger {
  background: #d4493f;
}
p {
  margin: 0 0 10px;
  font-size: clamp(22px, 2vw, 32px);
}
.subhead {
  margin-bottom: 8px;
}
#scanner {
  position: absolute;
  opacity: 0;
  pointer-events: none;
}
#search {
  width: 100%;
  max-width: 1200px;
  font-size: clamp(28px, 2.2vw, 38px);
  padding: 18px;
  border-radius: 14px;
  border: 2px solid #d8e2dc;
}
.search-tools {
  margin: 8px auto 0;
  max-width: 1200px;
  display: flex;
  justify-content: flex-end;
}
.search-tools button {
  border: 0;
  border-radius: 10px;
  padding: 12px 18px;
  font-size: 20px;
  background: #ecf1ed;
  color: #17332f;
}
#results {
  background: #fff;
  color: #111;
  margin: 6px auto 0;
  max-width: 1200px;
  border-radius: 12px;
  text-align: left;
  overflow: hidden;
  max-height: 24dvh;
  overflow-y: auto;
}
#results button {
  display: block;
  width: 100%;
  border: 0;
  border-top: 1px solid #ddd;
  background: #fff;
  padding: 18px;
  text-align: left;
  font-size: clamp(22px, 1.8vw, 30px);
  min-height: 86px;
}
.card {
  margin-top: 12px;
  background: rgba(255, 255, 255, .09);
  border: 1px solid rgba(255, 255, 255, .2);
  border-radius: 16px;
  padding: 14px;
  text-align: center;
}
.name {
  font-size: clamp(40px, 3.4vw, 58px);
  font-weight: 700;
}
.avatar {
  width: 220px;
  height: 220px;
  border-radius: 12px;
  object-fit: cover;
  border: 2px solid rgba(255,255,255,.35);
  display: block;
  margin: 0 auto 8px;
  background: rgba(255,255,255,.16);
}
.slot {
  font-size: clamp(76px, 7vw, 110px);
  color: #7dff99;
  font-weight: 800;
  margin: 6px 0;
}
.action-row {
  margin-top: 8px;
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 8px;
}
.btn {
  border: 0;
  border-radius: 14px;
  padding: 18px 20px;
  margin: 0;
  font-size: clamp(28px, 2.3vw, 38px);
  cursor: pointer;
  min-height: 82px;
  min-width: 0;
}
.btn-primary { background: #0d8f4a; color: #fff; }
.btn-warn { background: #f5c84b; color: #111; }
.error {
  background: #b03025;
  border-radius: 10px;
  padding: 12px;
  font-size: 28px;
}
.loading {
  font-size: 30px;
  margin-top: 8px;
}
.receipt {
  background: #fff;
  color: #111;
  border-radius: 14px;
  padding: 0;
  display: block;
  box-sizing: border-box;
  text-align: left;
  overflow: hidden;
  box-shadow: 0 10px 28px rgba(0, 0, 0, 0.22);
  width: min(100%, 760px);
  max-width: 100%;
  margin: 0 auto;
}
.receipt-head {
  background: linear-gradient(120deg, #153e37, #1d6d5f);
  color: #fff;
  padding: 12px 16px;
}
.receipt-logo {
  display: block;
  width: 140px;
  height: auto;
  margin: 0 0 8px;
  filter: brightness(0) invert(1);
}
.receipt-title {
  margin: 0;
  font-size: 30px;
  font-weight: 800;
}
.receipt-sub {
  margin: 4px 0 0;
  font-size: 16px;
  opacity: 0.92;
}
.receipt-body {
  padding: 14px 16px;
}
.receipt-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 8px 12px;
}
.receipt-item {
  background: #f3f7f4;
  border-radius: 10px;
  padding: 8px 10px;
}
.receipt-label {
  font-size: 12px;
  text-transform: uppercase;
  color: #5a6861;
  letter-spacing: .04em;
}
.receipt-value {
  font-size: 20px;
  font-weight: 700;
  margin-top: 2px;
  word-break: break-word;
}
.receipt-note {
  margin-top: 10px;
  border: 1px dashed #9aa9a2;
  border-radius: 10px;
  padding: 10px;
  font-size: 16px;
}
.qr-box {
  margin-top: 10px;
  border-top: 1px dashed #888;
  padding-top: 10px;
  overflow: hidden;
}
.qr-grid {
  display: flex;
  gap: 12px;
  flex-wrap: wrap;
  justify-content: center;
}
.qr-item {
  flex: 0 1 210px;
  width: 100%;
  max-width: min(170px, calc(100vw - 88px));
  text-align: center;
  margin: 0 auto;
}
.qr-title {
  font-size: 16px;
  font-weight: 700;
  margin-bottom: 6px;
}
.qr-text {
  font-size: 14px;
  word-break: break-all;
}
.qr-item img {
  display: block;
  width: 100%;
  max-width: min(170px, 100%);
  height: auto;
  margin: 0 auto;
}
.print-only { display: none; }
.osk {
  position: fixed;
  left: 10px;
  right: 10px;
  bottom: 8px;
  z-index: 30;
  background: rgba(8, 24, 22, 0.97);
  border: 1px solid rgba(255,255,255,.22);
  border-radius: 16px;
  padding: 12px;
  box-shadow: 0 12px 40px rgba(0,0,0,.42);
  display: none;
}
.osk.visible {
  display: block;
}
.osk-row {
  display: grid;
  grid-template-columns: repeat(12, minmax(0, 1fr));
  gap: 8px;
  margin-bottom: 8px;
}
.osk-row:last-child {
  margin-bottom: 0;
}
.osk-key {
  border: 0;
  border-radius: 12px;
  min-height: 78px;
  font-size: 30px;
  font-weight: 700;
  background: #e7efea;
  color: #12322f;
}
.osk-key.wide {
  grid-column: span 2;
  font-size: 24px;
}
.osk-key.space {
  grid-column: span 8;
}
.osk-key:active {
  transform: translateY(2px) scale(0.98);
}
.osk-key.pressed,
.osk-key:active {
  background: #ffd463;
  color: #0f2f2b;
  box-shadow: inset 0 0 0 3px rgba(15, 47, 43, 0.25);
}
.osk-key.special {
  background: #c7d5cd;
}
.stage {
  flex: 1;
  min-height: 0;
  overflow: hidden;
  display: flex;
  flex-direction: column;
}
#view {
  flex: 1;
  min-height: 0;
  overflow: hidden;
}
#view .card {
  height: 100%;
  box-sizing: border-box;
  display: flex;
  flex-direction: column;
  justify-content: center;
}
.card.receipt-card {
  justify-content: flex-start;
  align-items: center;
  overflow-y: hidden;
  overflow-x: hidden;
  padding-bottom: 10px;
  gap: 8px;
}
.receipt-stack {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
  transform-origin: top center;
}
.card.receipt-card .slot {
  flex: 0 0 auto;
}
.card.receipt-card .receipt {
  flex: 0 0 auto;
}
body.showing-card h1,
body.showing-card .subhead,
body.showing-card #search,
body.showing-card #results,
body.showing-card .search-tools {
  display: none;
}
body.showing-card .avatar {
  width: clamp(220px, 27vh, 320px);
  height: clamp(220px, 27vh, 320px);
}
@media (max-width: 780px) {
  .mode-switch {
    grid-template-columns: 1fr;
  }
  .top-tools {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
  h1 { font-size: 34px; }
  p { font-size: 20px; }
  #search { font-size: 24px; }
  .name { font-size: 34px; }
  .slot { font-size: 58px; }
  .avatar { width: 160px; height: 160px; }
  .action-row { grid-template-columns: 1fr; }
  .capacity-grid { grid-template-columns: 1fr; }
  .btn {
    width: 100%;
    font-size: 22px;
  }
  .receipt-head {
    padding: 10px 12px;
  }
  .receipt-title {
    font-size: 24px;
  }
  .receipt-sub {
    font-size: 14px;
  }
  .receipt-body {
    padding: 10px 12px;
  }
  .receipt-grid {
    grid-template-columns: 1fr;
    gap: 8px;
  }
  .receipt-value {
    font-size: 18px;
  }
  .qr-grid {
    flex-direction: column;
  }
  .qr-item {
    max-width: min(150px, calc(100vw - 72px));
  }
  .slot {
    font-size: clamp(42px, 12vw, 58px);
  }
}
@media (max-width: 1366px), (max-height: 800px) {
  main {
    padding: 12px 14px 16px;
  }
  .card {
    padding: 10px;
  }
  .slot {
    font-size: clamp(40px, 9vw, 72px);
    margin: 4px 0;
  }
  .receipt-head {
    padding: 8px 10px;
  }
  .receipt-logo {
    width: 120px;
    margin-bottom: 6px;
  }
  .receipt-title {
    font-size: 22px;
  }
  .receipt-sub {
    font-size: 13px;
  }
  .receipt-body {
    padding: 10px;
  }
  .receipt-note {
    font-size: 14px;
    padding: 8px;
  }
  .qr-item {
    max-width: min(150px, calc(100vw - 72px));
  }
  .qr-item img {
    max-width: min(150px, 100%);
  }
  .qr-title {
    font-size: 14px;
    margin-bottom: 4px;
  }
  .qr-text {
    font-size: 12px;
  }
}
@media (max-height: 760px) {
  body.showing-card .avatar {
    width: 180px;
    height: 180px;
  }
}
@media print {
  body { background: #fff; color: #111; }
  main > h1, main > p, #search, #results, #loading, .top-tools, .mode-switch { display: none !important; }
  #view { margin-top: 0; }
  .card { background: #fff; border: 1px solid #ddd; color: #111; }
  .print-only { display: block; }
}
</style>
</head>
<body>
<main>
  <div class="mode-switch">
    <button id="modePhone" class="mode-btn mode-phone active" type="button">Mobilhotell (slot/lading)</button>
    <button id="modeStorage" class="mode-btn mode-storage" type="button">Generell oppbevaring (scan inn/ut)</button>
  </div>

  <h1 id="pageTitle">Mobilhotell</h1>
  <p id="pageSubhead" class="subhead">Scan QR-kode for slot/lading, eller søk deltakernavn</p>

  <div class="top-tools">
    <button id="btnFocus" class="chip">Reaktiver scanner</button>
    <button id="btnReset" class="chip">Tøm skjerm</button>
    <span id="netState" class="chip">Online</span>
    <a id="adminQuickLink" class="chip chip-link" href="admin.php">Admin mobilhotell</a>
  </div>

  <section id="adminPanel" class="admin-panel">
    <div class="admin-panel-head">
      <h2>Kapasitet</h2>
      <a class="admin-link" href="admin.php">Åpne adminside</a>
    </div>
    <div id="capacityGrid" class="capacity-grid"></div>
  </section>

  <input id="scanner" autocomplete="off">
  <input id="search" type="search" autocomplete="off" placeholder="Søk navn eller QR">
  <div class="search-tools">
    <button id="btnClearSearch" type="button">Tøm søk</button>
  </div>
  <div id="results"></div>

  <div class="stage">
    <div id="loading" class="loading" style="display:none">Laster...</div>
    <div id="view"></div>
  </div>
</main>

<div id="osk" class="osk" aria-hidden="true">
  <div id="oskRows"></div>
</div>

<script>
(() => {
  const scanner = document.getElementById('scanner');
  const search = document.getElementById('search');
  const results = document.getElementById('results');
  const loading = document.getElementById('loading');
  const view = document.getElementById('view');
  const btnFocus = document.getElementById('btnFocus');
  const btnReset = document.getElementById('btnReset');
  const btnClearSearch = document.getElementById('btnClearSearch');
  const netState = document.getElementById('netState');
  const adminQuickLink = document.getElementById('adminQuickLink');
  const modePhone = document.getElementById('modePhone');
  const modeStorage = document.getElementById('modeStorage');
  const pageTitle = document.getElementById('pageTitle');
  const pageSubhead = document.getElementById('pageSubhead');
  const adminPanel = document.getElementById('adminPanel');
  const capacityGrid = document.getElementById('capacityGrid');
  const osk = document.getElementById('osk');
  const oskRows = document.getElementById('oskRows');

  let searchTimer = null;
  let resetTimer = null;
  let scanBuffer = '';
  let scanStart = 0;
  let scanLast = 0;
  let scanResetTimer = null;
  let lastScanQr = '';
  let lastScanAt = 0;
  let storageToggleInFlight = false;
  let lastStorageToggleQr = '';
  let lastStorageToggleAt = 0;
  let oskTarget = null;
  let oskShift = false;
  let currentMode = localStorage.getItem('mobilhotell_mode') === 'storage' ? 'storage' : 'phone';

  const oskLayout = [
    ['1','2','3','4','5','6','7','8','9','0','backspace'],
    ['q','w','e','r','t','y','u','i','o','p','å'],
    ['a','s','d','f','g','h','j','k','l','ø','æ'],
    ['shift','z','x','c','v','b','n','m',',','.','-'],
    ['space','enter','close']
  ];

  function isTextField(el) {
    if (!el) return false;
    if (el.id === 'scanner') return false;
    if (el.matches('input[type="text"], input[type="search"], input:not([type]), textarea')) return true;
    return false;
  }

  function getKeyLabel(key) {
    if (key === 'backspace') return 'Slett';
    if (key === 'shift') return 'Skift';
    if (key === 'space') return 'Mellomrom';
    if (key === 'enter') return 'Enter';
    if (key === 'close') return 'Lukk';
    if (oskShift && /^[a-zæøå]$/.test(key)) return key.toUpperCase();
    return key;
  }

  function createKeyboard() {
    oskRows.innerHTML = oskLayout.map((row) => {
      return '<div class="osk-row">' + row.map((key) => {
        const classes = ['osk-key'];
        if (key === 'backspace' || key === 'shift' || key === 'enter' || key === 'close') classes.push('wide', 'special');
        if (key === 'space') classes.push('space', 'special');
        return '<button type="button" class="' + classes.join(' ') + '" data-key="' + esc(key) + '">' + esc(getKeyLabel(key)) + '</button>';
      }).join('') + '</div>';
    }).join('');
  }

  function showKeyboard(target) {
    oskTarget = target;
    document.body.classList.add('keyboard-open');
    osk.classList.add('visible');
    osk.setAttribute('aria-hidden', 'false');
  }

  function hideKeyboard() {
    document.body.classList.remove('keyboard-open');
    osk.classList.remove('visible');
    osk.setAttribute('aria-hidden', 'true');
    oskTarget = null;
    oskShift = false;
    createKeyboard();
  }

  function insertText(target, text) {
    const start = target.selectionStart ?? target.value.length;
    const end = target.selectionEnd ?? target.value.length;
    const value = target.value;
    target.value = value.slice(0, start) + text + value.slice(end);
    const next = start + text.length;
    target.setSelectionRange(next, next);
    target.dispatchEvent(new Event('input', { bubbles: true }));
  }

  function doBackspace(target) {
    const start = target.selectionStart ?? target.value.length;
    const end = target.selectionEnd ?? target.value.length;
    if (start !== end) {
      target.value = target.value.slice(0, start) + target.value.slice(end);
      target.setSelectionRange(start, start);
    } else if (start > 0) {
      target.value = target.value.slice(0, start - 1) + target.value.slice(end);
      target.setSelectionRange(start - 1, start - 1);
    }
    target.dispatchEvent(new Event('input', { bubbles: true }));
  }

  function setLoading(on) {
    loading.style.display = on ? 'block' : 'none';
  }

  function formatHoursMinutes(totalSeconds) {
    const sec = Math.max(0, Number(totalSeconds || 0));
    const h = Math.floor(sec / 3600);
    const m = Math.floor((sec % 3600) / 60);
    return h + ' t ' + m + ' min';
  }

  function applyModeUI() {
    modePhone.classList.toggle('active', currentMode === 'phone');
    modeStorage.classList.toggle('active', currentMode === 'storage');
    document.body.classList.toggle('mode-phone', currentMode === 'phone');
    document.body.classList.toggle('mode-storage', currentMode === 'storage');

    if (currentMode === 'storage') {
      pageTitle.textContent = 'Generell oppbevaring';
      pageSubhead.textContent = 'Scan QR for å registrere inn eller ut';
      search.placeholder = 'Søk navn eller QR (generell oppbevaring)';
      adminPanel.hidden = true;
      adminQuickLink.textContent = 'Admin generell';
      adminQuickLink.href = 'admin_general.php';
    } else {
      pageTitle.textContent = 'Mobilhotell';
      pageSubhead.textContent = 'Scan QR-kode for slot/lading, eller søk deltakernavn';
      search.placeholder = 'Søk navn eller QR';
      adminPanel.hidden = false;
      adminQuickLink.textContent = 'Admin mobilhotell';
      adminQuickLink.href = 'admin.php';
    }
  }

  function setMode(mode) {
    currentMode = mode === 'storage' ? 'storage' : 'phone';
    localStorage.setItem('mobilhotell_mode', currentMode);
    applyModeUI();
    view.innerHTML = '';
    search.value = '';
    results.innerHTML = '';
    scanner.value = '';
    document.body.classList.remove('showing-card');
    hideKeyboard();
    scanner.focus();
  }

  function capacityFillClass(percent) {
    if (percent >= 90) return 'capacity-fill danger';
    if (percent >= 75) return 'capacity-fill warn';
    return 'capacity-fill';
  }

  async function loadCapacity() {
    try {
      const data = await json('admin_api.php?action=capacity');
      if (!data.success || !data.capacity) {
        capacityGrid.innerHTML = '<div class="capacity-card">Kunne ikke hente kapasitet</div>';
        return;
      }

      const c = data.capacity;
      const cards = [
        ['Oppbevaring', c.storage],
        ['Lading', c.charging],
        ['Totalt', c.overall],
      ];

      capacityGrid.innerHTML = cards.map(([label, stat]) => {
        const percent = Math.max(0, Math.min(100, Number(stat.percent || 0)));
        return '<div class="capacity-card">'
          + '<div class="capacity-label">' + esc(label) + '</div>'
          + '<div class="capacity-meta">' + Number(stat.occupied || 0) + ' / ' + Number(stat.total || 0) + ' (' + percent.toFixed(1) + '%)</div>'
          + '<div class="capacity-track"><div class="' + capacityFillClass(percent) + '" style="width:' + percent + '%"></div></div>'
          + '</div>';
      }).join('');
    } catch {
      capacityGrid.innerHTML = '<div class="capacity-card">Kunne ikke hente kapasitet</div>';
    }
  }

  function scheduleReset() {
    if (resetTimer) clearTimeout(resetTimer);
    resetTimer = setTimeout(() => {
      view.innerHTML = '';
      document.body.classList.remove('showing-card');
      hideKeyboard();
      loadCapacity();
    }, 12000);
  }

  function fitReceiptToViewport() {
    const card = view.querySelector('.receipt-card');
    const stack = view.querySelector('.receipt-stack');
    if (!card || !stack) return;

    stack.style.zoom = '1';

    const availableW = Math.max(0, card.clientWidth - 16);
    const availableH = Math.max(0, card.clientHeight - 16);
    const contentW = Math.max(1, stack.scrollWidth);
    const contentH = Math.max(1, stack.scrollHeight);
    const fitScale = Math.min(1, availableW / contentW, availableH / contentH);
    const preferredScale = 0.92;
    const scale = Math.min(preferredScale, fitScale);

    stack.style.zoom = String(scale);
  }

  scanner.focus();

  function updateNetState() {
    const online = navigator.onLine;
    netState.textContent = online ? 'Online' : 'Offline';
    netState.className = online ? 'chip' : 'chip offline';
  }

  async function json(url) {
    const res = await fetch(url, { cache: 'no-store' });
    if (!res.ok) {
      throw new Error('http_error');
    }
    return await res.json();
  }

  function esc(s) {
    return String(s ?? '').replace(/[&<>"']/g, (m) => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m]));
  }

  function resolveImage(path) {
    const p = String(path || '').trim();
    if (!p) return '';
    if (/^https?:\/\//i.test(p)) return p;
    if (p.startsWith('/')) return p;

    const basePath = window.location.pathname.replace(/\/[^/]*$/, '/');
    return basePath + p.replace(/^\.\//, '');
  }

  function normalizeQrInput(value) {
    return String(value || '').trim().replace(/[+＋–—]/g, '-');
  }

  async function lookupQr(qr) {
    qr = normalizeQrInput(qr);
    if (!qr) return;

    const now = Date.now();
    if (qr === lastScanQr && (now - lastScanAt) < 450) {
      return;
    }
    lastScanQr = qr;
    lastScanAt = now;

    if (currentMode === 'storage') {
      await toggleStorage({ qr });
      return;
    }

    setLoading(true);
    try {
      const data = await json('lookup.php?qr=' + encodeURIComponent(qr));
      if (!data.found) {
        view.innerHTML = '<div class="error">Deltaker ikke funnet</div>';
        scheduleReset();
        return;
      }
      renderParticipant(data);
      scheduleReset();
    } catch {
      view.innerHTML = '<div class="error">Feil ved oppslag</div>';
      scheduleReset();
    } finally {
      setLoading(false);
    }
  }

  async function toggleStorage(payload) {
    const qr = normalizeQrInput(payload.qr || '');
    const now = Date.now();

    if (storageToggleInFlight) {
      return;
    }

    if (qr && qr === lastStorageToggleQr && (now - lastStorageToggleAt) < 1400) {
      return;
    }

    storageToggleInFlight = true;
    setLoading(true);
    try {
      const params = new URLSearchParams();
      if (qr) params.set('qr', qr);
      if (payload.participant_id) params.set('participant_id', String(payload.participant_id));
      params.set('_ts', String(Date.now()));

      const data = await json('storage_toggle.php?' + params.toString());
      if (!data.success) {
        let msg = 'Feil i generell oppbevaring';
        if (data.error === 'participant_not_found') msg = 'Deltaker ikke funnet';
        else if (data.error === 'session_conflict') msg = 'Konflikt: deltaker er allerede registrert aktiv';
        else if (data.error === 'session_not_checked_in') msg = 'Kunne ikke registrere utlevering';
        else if (data.error === 'server_error') msg = 'Serverfeil ved inn/ut';
        if (data.error) msg += ' (' + String(data.error) + ')';
        view.innerHTML = '<div class="error">' + esc(msg) + '</div>';
        scheduleReset();
        return;
      }

      lastStorageToggleQr = normalizeQrInput(data.qr || qr);
      lastStorageToggleAt = Date.now();

      const isIn = data.action === 'checked_in';
      const title = isIn ? 'Innlevert i generell oppbevaring' : 'Utlevert fra generell oppbevaring';
      const periodText = isIn ? '-' : formatHoursMinutes(data.period_seconds || 0);
      const totalText = formatHoursMinutes(data.total_seconds || 0);

      view.innerHTML = '<div class="card">'
        + '<div class="slot">' + esc(isIn ? 'INN' : 'UT') + '</div>'
        + '<div class="name" style="font-size:36px">' + esc(data.name || '') + '</div>'
        + '<div style="font-size:28px; margin-top:8px"><strong>' + esc(title) + '</strong></div>'
        + '<div style="font-size:24px; margin-top:6px">Denne perioden: <strong>' + esc(periodText) + '</strong></div>'
        + '<div style="font-size:24px; margin-top:4px">Totalt i generell oppbevaring: <strong>' + esc(totalText) + '</strong></div>'
        + '</div>';

      document.body.classList.add('showing-card');
      hideKeyboard();
      scheduleReset();
    } catch {
      view.innerHTML = '<div class="error">Feil i generell oppbevaring</div>';
      scheduleReset();
    } finally {
      storageToggleInFlight = false;
      setLoading(false);
    }
  }

  function renderParticipant(p) {
    const screenMin = Math.floor((Number(p.screenfree_seconds || 0) % 3600) / 60);
    const screenH = Math.floor(Number(p.screenfree_seconds || 0) / 3600);

    let actions = '';
    if (p.checked_in && p.session_id) {
      actions = '<div class="slot">' + esc(p.slot) + '</div><button class="btn btn-primary" data-checkout="' + Number(p.session_id) + '">Registrer utlevert</button>';
    } else {
      actions = '<div class="action-row"><button class="btn btn-primary" data-checkin="storage">Oppbevar</button><button class="btn btn-warn" data-checkin="charging">Lad</button></div>';
    }

    view.innerHTML = '<div class="card">'
      + '<img class="avatar" src="' + esc(resolveImage(p.image)) + '" alt="Deltakerbilde">'
      + '<div class="name">' + esc(p.name) + '</div>'
      + '<div>' + esc(p.county) + ' - ' + esc(p.type) + '</div>'
      + '<div>Skjermfri tid: ' + screenH + ' t ' + screenMin + ' min</div>'
      + actions
      + '</div>';
    document.body.classList.add('showing-card');
    hideKeyboard();

    const c1 = view.querySelector('[data-checkin="storage"]');
    const c2 = view.querySelector('[data-checkin="charging"]');
    const out = view.querySelector('[data-checkout]');

    if (c1) c1.addEventListener('click', () => checkin(p.qr, 'storage'));
    if (c2) c2.addEventListener('click', () => checkin(p.qr, 'charging'));
    if (out) out.addEventListener('click', () => checkout(Number(out.dataset.checkout)));
  }

  async function checkin(qr, type) {
    setLoading(true);
    try {
      const data = await json('checkin.php?qr=' + encodeURIComponent(qr) + '&type=' + encodeURIComponent(type));
      if (!data.success) {
        let msg = 'Feil';
        if (data.error === 'already_checked_in') msg = 'Telefon allerede innlevert';
        else if (data.error === 'no_free_slot') msg = 'Ingen ledig slot akkurat nå';
        view.innerHTML = '<div class="error">' + msg + '</div>';
        return;
      }

      const deliveryType = type === 'charging' ? 'Lading' : 'Oppbevaring';

      view.innerHTML = '<div class="card receipt-card">'
        + '<div class="receipt-stack">'
        + '<div class="slot">' + esc(data.slot) + '</div>'
        + '<div class="receipt">'
        + '<div class="receipt-head">'
        + '<img class="receipt-logo" src="assets/UKM Logo Sort RGB.png" alt="UKM logo">'
        + '<div class="print-only" style="font-size:13px; margin-bottom:4px;">Mobilhotell kvittering</div>'
        + '<h2 class="receipt-title">Din mobil er trygg</h2>'
        + '<p class="receipt-sub">Vis denne ved utlevering</p>'
        + '</div>'
        + '<div class="receipt-body">'
        + '<div class="receipt-grid">'
        + '<div class="receipt-item"><div class="receipt-label">Navn</div><div class="receipt-value">' + esc(data.name) + '</div></div>'
        + '<div class="receipt-item"><div class="receipt-label">Type</div><div class="receipt-value">' + esc(deliveryType) + '</div></div>'
        + '<div class="receipt-item"><div class="receipt-label">Slot</div><div class="receipt-value">' + esc(data.slot) + '</div></div>'
        + '<div class="receipt-item"><div class="receipt-label">Tid</div><div class="receipt-value">' + esc(data.checked_in_at || '') + '</div></div>'
        + '</div>'
        + '<div class="receipt-note"><strong>Ved utlevering:</strong> Scan deltaker-ID og bekreft bilde.</div>'
        + '<div class="qr-box">'
        + '<div class="qr-grid">'
        + '<div class="qr-item">'
        + '<div class="qr-title">Deltaker-kode</div>'
        + '<img alt="Deltaker QR" width="170" height="170" src="https://api.qrserver.com/v1/create-qr-code/?size=170x170&data=' + encodeURIComponent(qr) + '">'
        + '<div class="qr-text">' + esc(qr) + '</div>'
        + '</div>'
        + '</div>'
        + '</div>'
        + '</div>'
        + '</div>'
        + '</div>'
        + '</div>';
      document.body.classList.add('showing-card');
      hideKeyboard();
      requestAnimationFrame(() => {
        fitReceiptToViewport();
        requestAnimationFrame(fitReceiptToViewport);
      });
      loadCapacity();
      scheduleReset();
    } catch {
      view.innerHTML = '<div class="error">Feil ved innsjekk</div>';
      scheduleReset();
    } finally {
      setLoading(false);
    }
  }

  async function checkout(sessionId) {
    setLoading(true);
    try {
      const data = await json('checkout.php?id=' + encodeURIComponent(String(sessionId)));
      if (!data.success) {
        view.innerHTML = '<div class="error">Kunne ikke registrere utlevering</div>';
      } else {
        const total = Number(data.screenfree_seconds || 0);
        const hours = Math.floor(total / 3600);
        const mins = Math.floor((total % 3600) / 60);
        view.innerHTML = '<div class="card">'
          + '<div class="slot">Utlevert</div>'
          + '<div class="name" style="font-size:34px">' + esc(data.name || '') + '</div>'
          + '<div style="font-size:30px; margin-top:8px">Samlet skjermfri tid: <strong>' + hours + ' t ' + mins + ' min</strong></div>'
          + '</div>';
        document.body.classList.add('showing-card');
        hideKeyboard();
        loadCapacity();
      }
      scheduleReset();
    } catch {
      view.innerHTML = '<div class="error">Feil ved utlevering</div>';
      scheduleReset();
    } finally {
      setLoading(false);
    }
  }

  scanner.addEventListener('keydown', (e) => {
    if (e.key !== 'Enter') return;
    e.preventDefault();
    const qr = normalizeQrInput(scanner.value);
    scanner.value = '';
    if (!qr) return;
    lookupQr(qr);
  });

  function resetScanBuffer() {
    scanBuffer = '';
    scanStart = 0;
    scanLast = 0;
    if (scanResetTimer) {
      clearTimeout(scanResetTimer);
      scanResetTimer = null;
    }
  }

  window.addEventListener('keydown', (e) => {
    if (e.ctrlKey || e.altKey || e.metaKey) return;

    if (e.key === 'Enter') {
      if (scanBuffer.length >= 5 && (performance.now() - scanStart) <= 900) {
        const qr = scanBuffer;
        resetScanBuffer();
        e.preventDefault();
        lookupQr(qr);
        return;
      }
      resetScanBuffer();
      return;
    }

    if (e.key.length !== 1) return;

    const now = performance.now();
    if (!scanBuffer) {
      scanBuffer = e.key;
      scanStart = now;
      scanLast = now;
    } else {
      const delta = now - scanLast;
      if (delta > 75) {
        scanBuffer = e.key;
        scanStart = now;
      } else {
        scanBuffer += e.key;
      }
      scanLast = now;
    }

    if (scanResetTimer) clearTimeout(scanResetTimer);
    scanResetTimer = setTimeout(resetScanBuffer, 120);
  }, true);

  search.addEventListener('input', () => {
    if (searchTimer) clearTimeout(searchTimer);
    searchTimer = setTimeout(async () => {
      const q = search.value.trim();
      if (q.length < 2) {
        results.innerHTML = '';
        return;
      }
      try {
        const data = await json('lookup.php?action=search&q=' + encodeURIComponent(q));
        const items = data.items || [];
        if (!items.length) {
          results.innerHTML = '<button disabled>Ingen treff</button>';
          return;
        }
        results.innerHTML = items.map((it) =>
          '<button data-id="' + Number(it.id) + '"><strong>' + esc(it.name || ((it.first_name || '') + ' ' + (it.last_name || ''))) + '</strong><br>'
          + esc(it.county) + ' - ' + esc(it.participant_type) + ' - ' + esc(it.qr_code)
          + '</button>'
        ).join('');
      } catch {
        results.innerHTML = '<button disabled>Feil ved søk</button>';
      }
    }, 80);
  });

  search.addEventListener('keydown', (e) => {
    if (e.key !== 'Enter') return;
    e.preventDefault();
    const qr = normalizeQrInput(search.value);
    if (!qr) return;
    search.value = '';
    results.innerHTML = '';
    lookupQr(qr);
  });

  modePhone.addEventListener('click', () => setMode('phone'));
  modeStorage.addEventListener('click', () => setMode('storage'));

  btnFocus.addEventListener('click', () => scanner.focus());
  btnReset.addEventListener('click', () => {
    view.innerHTML = '';
    results.innerHTML = '';
    search.value = '';
    scanner.value = '';
    document.body.classList.remove('showing-card');
    hideKeyboard();
    scanner.focus();
  });
  btnClearSearch.addEventListener('click', () => {
    search.value = '';
    results.innerHTML = '';
    search.focus();
  });
  window.addEventListener('online', updateNetState);
  window.addEventListener('offline', updateNetState);
  window.addEventListener('resize', fitReceiptToViewport);
  updateNetState();
  loadCapacity();
  applyModeUI();
  setInterval(loadCapacity, 15000);

  createKeyboard();

  document.addEventListener('focusin', (e) => {
    const t = e.target;
    if (isTextField(t)) {
      showKeyboard(t);
    }
  });

  document.addEventListener('focusout', () => {
    setTimeout(() => {
      const active = document.activeElement;
      if (!isTextField(active) && !osk.contains(active)) {
        hideKeyboard();
      }
    }, 0);
  });

  osk.addEventListener('mousedown', (e) => {
    e.preventDefault();
  });

  function clearPressedKeys() {
    osk.querySelectorAll('.osk-key.pressed').forEach((el) => el.classList.remove('pressed'));
  }

  osk.addEventListener('pointerdown', (e) => {
    const keyButton = e.target.closest('[data-key]');
    if (!keyButton) return;
    clearPressedKeys();
    keyButton.classList.add('pressed');
  });

  osk.addEventListener('pointerup', clearPressedKeys);
  osk.addEventListener('pointercancel', clearPressedKeys);
  osk.addEventListener('pointerleave', clearPressedKeys);

  osk.addEventListener('click', (e) => {
    const keyButton = e.target.closest('[data-key]');
    if (!keyButton || !oskTarget || !isTextField(oskTarget)) return;

    const key = keyButton.dataset.key;
    oskTarget.focus();

    if (key === 'close') {
      hideKeyboard();
      return;
    }
    if (key === 'shift') {
      oskShift = !oskShift;
      createKeyboard();
      return;
    }
    if (key === 'backspace') {
      doBackspace(oskTarget);
      return;
    }
    if (key === 'space') {
      insertText(oskTarget, ' ');
      return;
    }
    if (key === 'enter') {
      oskTarget.dispatchEvent(new KeyboardEvent('keydown', { key: 'Enter', bubbles: true }));
      return;
    }

    const out = oskShift ? key.toUpperCase() : key;
    insertText(oskTarget, out);
    if (oskShift) {
      oskShift = false;
      createKeyboard();
    }
  });

  results.addEventListener('click', async (e) => {
    const btn = e.target.closest('button[data-id]');
    if (!btn) return;
    const id = Number(btn.dataset.id);
    results.innerHTML = '';
    search.value = '';
    setLoading(true);
    try {
      if (currentMode === 'storage') {
        await toggleStorage({ participant_id: id });
        return;
      }

      const data = await json('lookup.php?participant_id=' + encodeURIComponent(String(id)));
      if (!data.found) {
        view.innerHTML = '<div class="error">Deltaker ikke funnet</div>';
      } else {
        renderParticipant(data);
      }
      scheduleReset();
    } catch {
      view.innerHTML = '<div class="error">Feil ved oppslag</div>';
      scheduleReset();
    } finally {
      setLoading(false);
    }
  });
})();
</script>
</body>
</html>
