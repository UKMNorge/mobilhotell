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
}
main {
  max-width: 1000px;
  margin: 0 auto;
  padding: 14px;
}
h1 {
  margin: 8px 0 2px;
  font-size: 42px;
}
p {
  margin: 0 0 10px;
  font-size: 22px;
}
#scanner {
  position: absolute;
  opacity: 0;
  pointer-events: none;
}
#search {
  width: 100%;
  max-width: 900px;
  font-size: 28px;
  padding: 16px;
  border-radius: 14px;
  border: 0;
}
#results {
  background: #fff;
  color: #111;
  margin: 6px auto 0;
  max-width: 900px;
  border-radius: 12px;
  text-align: left;
  overflow: hidden;
}
#results button {
  display: block;
  width: 100%;
  border: 0;
  border-top: 1px solid #ddd;
  background: #fff;
  padding: 14px;
  text-align: left;
  font-size: 22px;
}
.card {
  margin-top: 16px;
  background: rgba(255, 255, 255, .09);
  border: 1px solid rgba(255, 255, 255, .2);
  border-radius: 14px;
  padding: 16px;
}
.name {
  font-size: 44px;
  font-weight: 700;
}
.avatar {
  width: 170px;
  height: 170px;
  border-radius: 12px;
  object-fit: cover;
  border: 2px solid rgba(255,255,255,.35);
  margin-bottom: 10px;
  background: rgba(255,255,255,.16);
}
.slot {
  font-size: 88px;
  color: #7dff99;
  font-weight: 800;
  margin: 8px 0;
}
.btn {
  border: 0;
  border-radius: 12px;
  padding: 16px 24px;
  margin: 8px;
  font-size: 28px;
  cursor: pointer;
  min-height: 70px;
  min-width: 220px;
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
  font-size: 24px;
  margin-top: 10px;
}
.receipt {
  background: #fff;
  color: #111;
  border-radius: 10px;
  padding: 14px;
  display: inline-block;
  text-align: left;
}
.qr-box {
  margin-top: 10px;
  border-top: 1px dashed #888;
  padding-top: 10px;
}
.qr-text {
  font-size: 12px;
  word-break: break-all;
}
@media (max-width: 780px) {
  h1 { font-size: 32px; }
  p { font-size: 18px; }
  #search { font-size: 24px; }
  .name { font-size: 34px; }
  .slot { font-size: 68px; }
  .btn {
    width: calc(100% - 16px);
    margin: 8px;
    font-size: 26px;
  }
}
</style>
</head>
<body>
<main>
  <h1>Scan QR-kode</h1>
  <p>eller søk deltakernavn</p>

  <input id="scanner" autocomplete="off">
  <input id="search" type="search" autocomplete="off" placeholder="Søk navn eller QR">
  <div id="results"></div>

  <div id="loading" class="loading" style="display:none">Laster...</div>
  <div id="view"></div>
</main>

<script>
(() => {
  const scanner = document.getElementById('scanner');
  const search = document.getElementById('search');
  const results = document.getElementById('results');
  const loading = document.getElementById('loading');
  const view = document.getElementById('view');

  let searchTimer = null;
  let resetTimer = null;
  let scanBuffer = '';
  let scanStart = 0;
  let scanLast = 0;
  let scanResetTimer = null;

  function setLoading(on) {
    loading.style.display = on ? 'block' : 'none';
  }

  function scheduleReset() {
    if (resetTimer) clearTimeout(resetTimer);
    resetTimer = setTimeout(() => {
      view.innerHTML = '';
    }, 12000);
  }

  scanner.focus();

  async function json(url) {
    const res = await fetch(url);
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

  async function lookupQr(qr) {
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

  function renderParticipant(p) {
    const screenMin = Math.floor((Number(p.screenfree_seconds || 0) % 3600) / 60);
    const screenH = Math.floor(Number(p.screenfree_seconds || 0) / 3600);

    let actions = '';
    if (p.checked_in && p.session_id) {
      actions = '<div class="slot">' + esc(p.slot) + '</div><button class="btn btn-primary" data-checkout="' + Number(p.session_id) + '">Registrer utlevert</button>';
    } else {
      actions = '<button class="btn btn-primary" data-checkin="storage">Oppbevar</button><button class="btn btn-warn" data-checkin="charging">Lad</button>';
    }

    view.innerHTML = '<div class="card">'
      + '<img class="avatar" src="' + esc(resolveImage(p.image)) + '" alt="Deltakerbilde">'
      + '<div class="name">' + esc(p.name) + '</div>'
      + '<div>' + esc(p.county) + ' - ' + esc(p.type) + '</div>'
      + '<div>Skjermfri tid: ' + screenH + ' t ' + screenMin + ' min</div>'
      + actions
      + '</div>';

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

      view.innerHTML = '<div class="card">'
        + '<div class="slot">' + esc(data.slot) + '</div>'
        + '<div class="receipt">'
        + '<h2>Kvittering</h2>'
        + '<div><strong>Navn:</strong> ' + esc(data.name) + '</div>'
        + '<div><strong>Slot:</strong> ' + esc(data.slot) + '</div>'
        + '<div><strong>Tid:</strong> ' + esc(data.checked_in_at || '') + '</div>'
        + '<div class="qr-box">'
        + '<img alt="QR" width="180" height="180" src="https://api.qrserver.com/v1/create-qr-code/?size=180x180&data=' + encodeURIComponent(data.checkout_qr_text || '') + '">'
        + '<div class="qr-text">' + esc(data.checkout_qr_text || '') + '</div>'
        + '</div>'
        + '</div>'
        + '</div>';
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
    const qr = scanner.value.trim();
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
    }, 120);
  });

  search.addEventListener('keydown', (e) => {
    if (e.key !== 'Enter') return;
    e.preventDefault();
    const qr = search.value.trim();
    if (!qr) return;
    search.value = '';
    results.innerHTML = '';
    lookupQr(qr);
  });

  results.addEventListener('click', async (e) => {
    const btn = e.target.closest('button[data-id]');
    if (!btn) return;
    const id = Number(btn.dataset.id);
    results.innerHTML = '';
    search.value = '';
    setLoading(true);
    try {
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
