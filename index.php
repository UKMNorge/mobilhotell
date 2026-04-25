<!doctype html>
<html lang="no">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>UKM Mobilhotell</title>
<style>
body {
  margin: 0;
  background: linear-gradient(180deg, #10211e 0%, #132d2a 35%, #1d4540 100%);
  color: #fff;
  font-family: "Trebuchet MS", "Segoe UI", sans-serif;
  text-align: center;
}
h1 { font-size: 48px; margin-bottom: 8px; }
.subtitle { font-size: 22px; opacity: 0.9; }
#scanner {
  opacity: 0;
  position: absolute;
}
.search-wrap {
  width: min(900px, calc(100% - 28px));
  margin: 14px auto;
  position: relative;
}
#nameSearch {
  width: 100%;
  font-size: 26px;
  border: none;
  border-radius: 14px;
  padding: 14px 16px;
}
#nameResults {
  position: absolute;
  top: calc(100% + 4px);
  left: 0;
  right: 0;
  background: #fff;
  color: #111;
  border-radius: 12px;
  text-align: left;
  max-height: 300px;
  overflow: auto;
  box-shadow: 0 8px 20px rgba(0, 0, 0, 0.25);
}
.name-row {
  width: 100%;
  padding: 12px;
  border: none;
  background: #fff;
  text-align: left;
  font-size: 18px;
}
.name-row + .name-row {
  border-top: 1px solid #ddd;
}
.card {
  margin: 24px auto;
  width: min(1020px, calc(100% - 24px));
  background: rgba(255, 255, 255, 0.06);
  border: 1px solid rgba(255, 255, 255, 0.2);
  border-radius: 16px;
  padding: 22px;
}
.name {
  font-size: 50px;
  font-weight: bold;
}
.info {
  font-size: 30px;
}
.slot {
  font-size: 120px;
  color: #6eff8a;
  margin: 30px;
  font-weight: 800;
}
button {
  font-size: 34px;
  padding: 24px 42px;
  margin: 14px;
  border-radius: 15px;
  border: none;
  font-weight: 700;
  cursor: pointer;
}
.error {
  font-size: 44px;
  color: #ffd6d2;
  background: rgba(215, 43, 30, 0.65);
  border-radius: 12px;
  padding: 14px;
}
.loading {
  font-size: 30px;
  margin-top: 10px;
}
.receipt {
  margin-top: 18px;
  background: #fff;
  color: #111;
  border-radius: 12px;
  padding: 16px;
  display: inline-block;
  min-width: 320px;
}
.receipt h2 {
  margin: 0 0 8px;
  font-size: 30px;
}
.receipt-line {
  font-size: 20px;
  margin: 4px 0;
}
.qr-box {
  margin-top: 10px;
  padding-top: 10px;
  border-top: 1px dashed #999;
}
.qr-text {
  font-size: 13px;
  word-break: break-all;
}
@media (max-width: 780px) {
  h1 { font-size: 34px; }
  .subtitle { font-size: 18px; }
  .name { font-size: 36px; }
  .info { font-size: 22px; }
  .slot { font-size: 88px; }
  button {
    width: calc(100% - 24px);
    font-size: 28px;
    margin: 8px 12px;
  }
}
</style>
</head>
<body>
<div id="app"></div>
<script type="module" src="public/index.js"></script>

</body>
</html>
