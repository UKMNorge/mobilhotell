<!doctype html>
<html lang="no">
<head>
<meta charset="utf-8">
<title>UKM Mobilhotell</title>
<style>
body {
  margin: 0;
  background: #111;
  color: white;
  font-family: Arial, sans-serif;
  text-align: center;
}
h1 { font-size: 56px; }
#scanner {
  opacity: 0;
  position: absolute;
}
.card {
  margin-top: 40px;
}
.name {
  font-size: 52px;
  font-weight: bold;
}
.info {
  font-size: 32px;
}
.slot {
  font-size: 80px;
  color: #00ff99;
  margin: 30px;
}
button {
  font-size: 36px;
  padding: 25px 45px;
  margin: 20px;
  border-radius: 15px;
}
.error {
  font-size: 48px;
  color: #ff6666;
}
</style>
</head>
<body>

<h1>Scan QR-kode</h1>
<input id="scanner" autofocus autocomplete="off">
<div id="result"></div>

<script>
const input = document.getElementById('scanner');
const result = document.getElementById('result');

function focusScanner() {
  input.focus();
}

setInterval(focusScanner, 300);
focusScanner();

input.addEventListener('keydown', e => {
  if (e.key === 'Enter') {
    const qr = input.value.trim();
    input.value = '';

    if (!qr) return;

    fetch('lookup.php?qr=' + encodeURIComponent(qr))
      .then(r => r.json())
      .then(render)
      .catch(() => {
        result.innerHTML = '<div class="error">Feil ved oppslag</div>';
      });
  }
});

function formatTime(seconds) {
  const h = Math.floor(seconds / 3600);
  const m = Math.floor((seconds % 3600) / 60);
  return h + ' t ' + m + ' min';
}

function render(data) {
  if (!data.found) {
    result.innerHTML = '<div class="error">Deltaker ikke funnet</div>';
    return;
  }

  let html = `
    <div class="card">
      <div class="name">${data.name}</div>
      <div class="info">${data.county}</div>
      <div class="info">${data.type}</div>
      <div class="info">Skjermfri tid: ${formatTime(data.screenfree_seconds)}</div>
  `;

  if (data.checked_in) {
    html += `
      <div class="slot">Slot ${data.slot}</div>
      <button onclick="checkout(${data.session_id})">Registrer utlevert</button>
    `;
  } else {
    html += `
      <button onclick="checkin('${data.qr}', 'storage')">Oppbevar</button>
      <button onclick="checkin('${data.qr}', 'charging')">Lad</button>
    `;
  }

  html += '</div>';
  result.innerHTML = html;
}

function checkin(qr, type) {
  fetch('checkin.php?qr=' + encodeURIComponent(qr) + '&type=' + encodeURIComponent(type))
    .then(r => r.json())
    .then(data => {
      if (!data.success) {
        if (data.error === 'already_checked_in') {
          result.innerHTML = '<div class="error">Telefon allerede innlevert</div>';
        } else {
          result.innerHTML = '<div class="error">Feil: ' + data.error + '</div>';
        }
        return;
      }

    result.innerHTML = `
      <div class="slot" style="font-size:120px;">${data.slot}</div>
      <div class="info">LEGG TELEFON HER</div>
      <div class="info">${data.name}</div>
    `;
    });
}

function checkout(id) {
  fetch('checkout.php?id=' + encodeURIComponent(id))
    .then(r => r.json())
    .then(data => {
      if (data.success) {
        result.innerHTML = '<div class="slot">Utlevert</div>';
      } else {
        result.innerHTML = '<div class="error">Kunne ikke registrere utlevering</div>';
      }
    });
}

let resetTimer = null;

function scheduleReset() {
  if (resetTimer) {
    clearTimeout(resetTimer);
  }

  resetTimer = setTimeout(() => {
    result.innerHTML = '';
  }, 8000);
}
</script>

</body>
</html>
