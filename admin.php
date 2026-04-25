<!doctype html>
<html lang="no">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>UKM Mobilhotell Admin</title>
<style>
:root {
  --bg: #f6f7f4;
  --card: #ffffff;
  --ink: #111111;
  --muted: #5f6660;
  --line: #d8ddd8;
  --green: #0a8f45;
  --red: #d52b1e;
  --gray: #838383;
  --accent: #005e53;
}
* { box-sizing: border-box; }
body {
  margin: 0;
  background: radial-gradient(circle at 20% 10%, #e8f6ef, var(--bg) 55%);
  color: var(--ink);
  font-family: "Trebuchet MS", "Segoe UI", sans-serif;
}
header {
  padding: 18px;
  background: linear-gradient(90deg, #00332d, #006555);
  color: #fff;
}
h1 {
  margin: 0;
  font-size: 34px;
}
main {
  padding: 18px;
  display: grid;
  gap: 18px;
}
.panel {
  background: var(--card);
  border: 1px solid var(--line);
  border-radius: 14px;
  padding: 16px;
  box-shadow: 0 8px 18px rgba(0, 0, 0, 0.06);
}
.controls {
  display: grid;
  grid-template-columns: 1fr auto auto;
  gap: 10px;
}
input[type="search"] {
  width: 100%;
  font-size: 20px;
  padding: 14px 16px;
  border: 2px solid var(--line);
  border-radius: 12px;
}
button {
  font-size: 20px;
  font-weight: 700;
  border: none;
  border-radius: 12px;
  padding: 14px 18px;
  cursor: pointer;
}
button.primary { background: var(--accent); color: #fff; }
button.warn { background: #ffcf44; }
button.danger { background: var(--red); color: #fff; }
button.ghost { background: #edf1ed; }
#loading {
  display: none;
  padding: 10px 0;
  font-size: 18px;
  color: var(--muted);
}
#message {
  min-height: 26px;
  font-size: 20px;
  font-weight: 700;
}
#message.error { color: var(--red); }
#message.success { color: var(--green); }
.table-wrap { overflow: auto; }
table {
  width: 100%;
  border-collapse: collapse;
}
th, td {
  border-bottom: 1px solid var(--line);
  padding: 10px 8px;
  text-align: left;
  font-size: 18px;
}
th { font-size: 16px; text-transform: uppercase; color: var(--muted); }
.slot-badge {
  font-size: 28px;
  font-weight: 800;
  letter-spacing: 1px;
}
.grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(92px, 1fr));
  gap: 8px;
}
.slot-btn {
  border: 1px solid var(--line);
  border-radius: 10px;
  min-height: 78px;
  padding: 6px;
  text-align: center;
  font-size: 16px;
  font-weight: 700;
  color: #fff;
}
.slot-btn.free { background: var(--green); }
.slot-btn.busy { background: var(--red); }
.slot-btn.disabled { background: var(--gray); }
.slot-mini {
  display: block;
  font-size: 13px;
  opacity: 0.85;
}
.slot-detail {
  margin-top: 10px;
  padding: 12px;
  border-radius: 10px;
  background: #f1f4f1;
  font-size: 18px;
}
.actions {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}
@media (max-width: 780px) {
  h1 { font-size: 28px; }
  .controls { grid-template-columns: 1fr; }
  button { width: 100%; }
  th, td { font-size: 16px; }
}
</style>
</head>
<body>
<div id="app"></div>
<script type="module" src="public/admin.js"></script>
</body>
</html>
