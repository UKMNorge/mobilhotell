<!doctype html>
<html lang="no">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Mobilhotell Status</title>
<style>
body { margin: 0; font-family: Arial, sans-serif; background: #f3f6f4; color: #111; }
main { max-width: 1100px; margin: 0 auto; padding: 14px; display: grid; gap: 12px; }
.card { background: #fff; border: 1px solid #d8dfda; border-radius: 10px; padding: 12px; }
.grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 8px; }
.kpi { background: #edf3ee; border-radius: 8px; padding: 10px; }
.kpi .label { font-size: 12px; color: #4e5953; text-transform: uppercase; }
.kpi .value { font-size: 28px; font-weight: 700; }
#events { max-height: 360px; overflow: auto; }
table { width: 100%; border-collapse: collapse; }
th, td { border-bottom: 1px solid #e5ece7; padding: 6px; text-align: left; font-size: 13px; }
.tools { display: flex; gap: 8px; flex-wrap: wrap; }
button, a { padding: 10px 12px; border-radius: 8px; border: 0; background: #055548; color: #fff; text-decoration: none; cursor: pointer; }
</style>
</head>
<body>
<main>
  <div class="tools">
    <button id="refresh">Oppdater</button>
    <a href="admin.php">Admin</a>
    <a href="index.php">Innsjekk</a>
  </div>

  <section class="card">
    <h2>Status</h2>
    <div id="summary" class="grid"></div>
  </section>

  <section class="card">
    <h2>Siste hendelser</h2>
    <div id="events"></div>
  </section>
</main>

<script>
(() => {
  const summary = document.getElementById('summary');
  const events = document.getElementById('events');
  const refresh = document.getElementById('refresh');

  function esc(s) {
    return String(s ?? '').replace(/[&<>"']/g, (m) => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m]));
  }

  async function api(url) {
    const res = await fetch(url);
    return await res.json();
  }

  async function load() {
    const [health, log] = await Promise.all([
      api('admin_api.php?action=health'),
      api('admin_api.php?action=recent_events&limit=100')
    ]);

    const s = health.summary || {};
    const items = [
      ['Aktive', s.active_checkins || 0],
      ['Slots totalt', s.slots_total || 0],
      ['Slots aktive', s.slots_active || 0],
      ['Slots ledige', s.slots_free_active || 0],
      ['Deltakere', s.participants_total || 0],
      ['Server tid', s.server_time || '-']
    ];

    summary.innerHTML = items.map(([k, v]) =>
      '<div class="kpi"><div class="label">' + esc(k) + '</div><div class="value">' + esc(v) + '</div></div>'
    ).join('');

    const rows = log.items || [];
    if (!rows.length) {
      events.innerHTML = 'Ingen hendelser enda';
      return;
    }

    events.innerHTML = '<table><thead><tr><th>Tid</th><th>Type</th><th>Melding</th><th>Data</th></tr></thead><tbody>'
      + rows.map((r) =>
        '<tr><td>' + esc(r.created_at || '') + '</td><td>' + esc(r.event_type || '') + '</td><td>' + esc(r.message || '') + '</td><td>' + esc(r.metadata ? JSON.stringify(r.metadata) : '') + '</td></tr>'
      ).join('')
      + '</tbody></table>';
  }

  refresh.addEventListener('click', () => { void load(); });
  setInterval(() => { void load(); }, 10000);
  void load();
})();
</script>
</body>
</html>
