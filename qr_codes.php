<?php

declare(strict_types=1);

require __DIR__ . '/db.php';

$pdo = db();
$rows = $pdo->query('SELECT qr_code, first_name, last_name, county, participant_type, image_path FROM participants ORDER BY first_name, last_name')->fetchAll();

function esc(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}
?>
<!doctype html>
<html lang="no">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>QR-koder deltakere</title>
<style>
body { font-family: Arial, sans-serif; margin: 0; background: #f3f5f4; color: #111; }
main { max-width: 1200px; margin: 0 auto; padding: 16px; }
.grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 12px; }
.card { background: #fff; border: 1px solid #d6dcda; border-radius: 10px; padding: 10px; }
.avatar { width: 100%; height: 160px; object-fit: cover; border-radius: 8px; background: #eef1f0; }
.name { font-size: 20px; font-weight: 700; margin-top: 8px; }
.meta { font-size: 14px; color: #45514d; }
.qr { margin-top: 8px; display: block; width: 160px; height: 160px; }
.code { font-family: monospace; font-size: 14px; margin-top: 4px; word-break: break-all; }
.tools { margin-bottom: 10px; display: flex; gap: 8px; flex-wrap: wrap; }
button { border: 0; border-radius: 8px; padding: 10px 12px; cursor: pointer; background: #055548; color: #fff; }
@media print {
  .tools { display: none; }
  body { background: #fff; }
  .card { break-inside: avoid; }
}
</style>
</head>
<body>
<main>
  <h1>QR-koder for deltakere</h1>
  <div class="tools">
    <button type="button" onclick="window.print()">Skriv ut</button>
    <a href="index.php">Til innsjekk</a>
  </div>

  <div class="grid">
    <?php foreach ($rows as $r):
      $name = trim(($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? ''));
      $qr = (string)($r['qr_code'] ?? '');
      $img = (string)($r['image_path'] ?? '');
      $qrImg = 'https://api.qrserver.com/v1/create-qr-code/?size=160x160&data=' . rawurlencode($qr);
    ?>
      <div class="card">
        <img class="avatar" src="<?= esc($img) ?>" alt="<?= esc($name) ?>" onerror="this.style.display='none'">
        <div class="name"><?= esc($name) ?></div>
        <div class="meta"><?= esc((string)($r['county'] ?? '')) ?> - <?= esc((string)($r['participant_type'] ?? '')) ?></div>
        <img class="qr" src="<?= esc($qrImg) ?>" alt="QR <?= esc($qr) ?>">
        <div class="code"><?= esc($qr) ?></div>
      </div>
    <?php endforeach; ?>
  </div>
</main>
</body>
</html>
