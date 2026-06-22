<?php

declare(strict_types=1);

require __DIR__ . '/db.php';

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function parse_date_input(string $value, string $fallback): string
{
    $value = trim($value);
    if ($value === '') {
        return $fallback;
    }

    if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m)) {
        return $fallback;
    }

    $y = (int)$m[1];
    $mo = (int)$m[2];
    $d = (int)$m[3];
    if (!checkdate($mo, $d, $y)) {
        return $fallback;
    }

    return sprintf('%04d-%02d-%02d', $y, $mo, $d);
}

function format_duration(?int $seconds): string
{
    if ($seconds === null || $seconds < 0) {
        return '-';
    }

    $hours = intdiv($seconds, 3600);
    $minutes = intdiv($seconds % 3600, 60);
    return sprintf('%02dt %02dm', $hours, $minutes);
}

function day_list(string $fromDate, string $toDate): array
{
    $days = [];
    $cursor = strtotime($fromDate . ' 00:00:00');
    $end = strtotime($toDate . ' 00:00:00');
    if ($cursor === false || $end === false || $cursor > $end) {
        return [];
    }

    while ($cursor <= $end) {
      $days[] = date('Y-m-d', $cursor);
        $cursor = strtotime('+1 day', $cursor);
    }

    return $days;
}

function status_for_day(array $row, string $day, string $now): string
{
  $hasCheckinBeforeDeadline = (int)($row['has_checkin_before_deadline'] ?? 0) === 1;
  $spansDetoxDeadline = (int)($row['spans_detox_deadline'] ?? 0) === 1;
  $lastCheckout = (string)($row['last_checkout'] ?? '');

    $detoxDeadline = $day . ' 18:30:00';

  if (!$hasCheckinBeforeDeadline) {
        return 'not_eligible';
    }

  if ($spansDetoxDeadline && $now >= $detoxDeadline) {
        return 'completed';
    }

    if ($now < $detoxDeadline) {
      if ($lastCheckout !== '' && $lastCheckout < $detoxDeadline && !$spansDetoxDeadline) {
        return 'failed';
      }
      return 'in_progress';
    }

    return 'failed';
}

function status_label(string $status): string
{
    return match ($status) {
    'completed' => 'Fullført',
    'in_progress' => 'Pågår',
        'failed' => 'Brutt',
    default => 'Ikke kvalifisert',
    };
}

function compute_duration_seconds(array $row, string $day): ?int
{
    $firstCheckin = (string)($row['first_checkin'] ?? '');
    if ($firstCheckin === '') {
        return null;
    }

    $end = (string)($row['last_checkout'] ?? '');
    if ($end === '') {
        $end = $day . ' 18:30:00';
    }

    $startTs = strtotime($firstCheckin);
    $detoxStartTs = strtotime($day . ' 09:30:00');
    $detoxEndTs = strtotime($day . ' 18:30:00');
    $endTs = strtotime($end);
    if ($startTs === false || $detoxStartTs === false || $detoxEndTs === false || $endTs === false) {
        return null;
    }

    $startTs = max($startTs, $detoxStartTs);
    $endTs = min($endTs, $detoxEndTs);

    return max(0, $endTs - $startTs);
}

$pdo = db();
$clock = $pdo->query("SELECT DATE_FORMAT(CURDATE(), '%Y-%m-%d') AS today_local, DATE_FORMAT(NOW(), '%Y-%m-%d %H:%i:%s') AS now_local")->fetch();

$today = (string)($clock['today_local'] ?? date('Y-m-d'));
$fromDefault = $today;
$toDefault = $today;

$fromDate = parse_date_input((string)($_GET['from'] ?? ''), $fromDefault);
$toDate = parse_date_input((string)($_GET['to'] ?? ''), $toDefault);
if ($fromDate > $toDate) {
    $tmp = $fromDate;
    $fromDate = $toDate;
    $toDate = $tmp;
}

$days = day_list($fromDate, $toDate);
if (count($days) > 31) {
    $days = array_slice($days, 0, 31);
    $toDate = end($days) ?: $toDate;
}

$now = (string)($clock['now_local'] ?? date('Y-m-d H:i:s'));

$stmt = $pdo->prepare("SELECT
    p.id AS participant_id,
    p.qr_code,
    p.first_name,
    p.last_name,
    p.participant_type,
    MIN(CASE
      WHEN ps.checkin_time < ?
       AND (ps.checkout_time IS NULL OR ps.checkout_time >= ?)
      THEN GREATEST(ps.checkin_time, ?)
      ELSE NULL END) AS first_checkin,
    MAX(CASE
      WHEN ps.status = 'checked_out'
      THEN LEAST(ps.checkout_time, ?)
      ELSE NULL END) AS last_checkout,
    MAX(CASE
      WHEN ps.checkin_time < ?
       AND (ps.checkout_time IS NULL OR ps.checkout_time >= ?)
      THEN 1 ELSE 0 END) AS has_checkin_before_deadline,
    MAX(CASE
      WHEN ps.checkin_time < ?
       AND (ps.checkout_time IS NULL OR ps.checkout_time >= ?)
      THEN 1 ELSE 0 END) AS spans_detox_deadline
  FROM phone_sessions ps
  JOIN participants p ON p.id = ps.participant_id
  WHERE ps.checkin_time < ?
    AND (ps.checkout_time IS NULL OR ps.checkout_time >= ?)
  GROUP BY p.id, p.qr_code, p.first_name, p.last_name, p.participant_type
  ORDER BY p.last_name ASC, p.first_name ASC");

$rows = [];
foreach ($days as $day) {
    $dayStart = $day . ' 00:00:00';
    $nextDay = date('Y-m-d 00:00:00', strtotime($day . ' +1 day'));
    $checkinDeadline = $day . ' 09:30:00';
    $detoxDeadline = $day . ' 18:30:00';

    $stmt->execute([
        $checkinDeadline,
        $dayStart,
        $checkinDeadline,
        $detoxDeadline,
        $checkinDeadline,
        $checkinDeadline,
        $checkinDeadline,
        $detoxDeadline,
        $nextDay,
        $dayStart,
    ]);

    $dayRows = $stmt->fetchAll();
    foreach ($dayRows as $row) {
        $row['day'] = $day;
        $rows[] = $row;
    }
}

$summary = [];
$roleSummary = [];
$details = [];

foreach ($days as $day) {
    $summary[$day] = [
        'eligible' => 0,
        'completed' => 0,
        'in_progress' => 0,
        'failed' => 0,
    ];
}

foreach ($rows as $row) {
    $day = (string)$row['day'];
    if (!isset($summary[$day])) {
        continue;
    }

    $status = status_for_day($row, $day, $now);
    if ($status === 'not_eligible') {
        continue;
    }

    $summary[$day]['eligible']++;
    $summary[$day][$status]++;

    $role = trim((string)($row['participant_type'] ?? ''));
    if ($role === '') {
        $role = 'Ukjent';
    }

    if (!isset($roleSummary[$role])) {
        $roleSummary[$role] = [
            'eligible' => 0,
            'completed' => 0,
            'in_progress' => 0,
            'failed' => 0,
        ];
    }

    $roleSummary[$role]['eligible']++;
    $roleSummary[$role][$status]++;

    $details[] = [
        'day' => $day,
        'name' => trim((string)$row['first_name'] . ' ' . (string)$row['last_name']),
        'qr_code' => (string)$row['qr_code'],
        'participant_type' => $role,
        'first_checkin' => (string)$row['first_checkin'],
        'last_checkout' => (string)($row['last_checkout'] ?? ''),
        'status' => $status,
        'duration_seconds' => compute_duration_seconds($row, $day),
    ];
}

usort($details, static function (array $a, array $b): int {
  $rank = [
    'completed' => 0,
    'in_progress' => 1,
    'failed' => 2,
    'not_eligible' => 3,
  ];

  $aRank = $rank[$a['status']] ?? 99;
  $bRank = $rank[$b['status']] ?? 99;
  if ($aRank !== $bRank) {
    return $aRank <=> $bRank;
  }

    if ($a['day'] !== $b['day']) {
        return strcmp($b['day'], $a['day']);
    }
    return strcmp($a['name'], $b['name']);
});

ksort($roleSummary);

$totalEligible = 0;
$totalCompleted = 0;
$totalInProgress = 0;
$totalFailed = 0;

foreach ($summary as $stats) {
    $totalEligible += $stats['eligible'];
    $totalCompleted += $stats['completed'];
    $totalInProgress += $stats['in_progress'];
    $totalFailed += $stats['failed'];
}

$successRate = $totalEligible > 0 ? round(($totalCompleted / $totalEligible) * 100, 1) : 0.0;

?><!doctype html>
<html lang="no">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Digital Detox statistikk</title>
  <style>
    :root {
      --bg: #f6f6ef;
      --card: #fffef9;
      --ink: #15232d;
      --muted: #54626b;
      --line: #d6dbd2;
      --accent: #03624c;
      --good: #2d7a39;
      --warn: #9a6a08;
      --bad: #a33030;
    }
    * { box-sizing: border-box; }
    body {
      margin: 0;
      font-family: "Source Sans 3", "Segoe UI", sans-serif;
      background: radial-gradient(circle at top right, #d5e8df 0%, var(--bg) 45%, #f0e9dd 100%);
      color: var(--ink);
    }
    main { max-width: 1200px; margin: 24px auto; padding: 0 16px 24px; }
    .top-actions { margin: 0 0 12px; }
    .back-link {
      display: inline-block;
      text-decoration: none;
      border: 1px solid #bcc8be;
      border-radius: 8px;
      background: #fff;
      color: var(--ink);
      font-size: 15px;
      font-weight: 700;
      padding: 8px 12px;
    }
    .back-link:hover {
      background: #f4f8f5;
    }
    h1 { margin: 0 0 14px; font-size: 34px; }
    .card {
      background: var(--card);
      border: 1px solid var(--line);
      border-radius: 12px;
      padding: 14px;
      margin-bottom: 16px;
      box-shadow: 0 8px 24px rgba(0, 0, 0, 0.04);
    }
    form { display: flex; flex-wrap: wrap; gap: 10px; align-items: end; }
    label { display: flex; flex-direction: column; font-weight: 600; gap: 4px; }
    input[type="date"] {
      border: 1px solid #bcc8be;
      border-radius: 8px;
      padding: 8px 10px;
      font-size: 16px;
      min-width: 170px;
      background: #fff;
    }
    button {
      border: 0;
      border-radius: 8px;
      background: var(--accent);
      color: #fff;
      font-size: 16px;
      font-weight: 700;
      padding: 9px 16px;
      cursor: pointer;
    }
    .kpis {
      display: grid;
      grid-template-columns: repeat(5, minmax(140px, 1fr));
      gap: 10px;
    }
    .kpi {
      border: 1px solid var(--line);
      border-radius: 10px;
      background: #fff;
      padding: 10px;
    }
    .kpi .label { color: var(--muted); text-transform: uppercase; font-size: 12px; }
    .kpi .value { font-size: 28px; font-weight: 700; line-height: 1.2; }
    table { width: 100%; border-collapse: collapse; }
    th, td { border-bottom: 1px solid var(--line); padding: 8px 6px; text-align: left; font-size: 14px; }
    th { font-size: 12px; text-transform: uppercase; letter-spacing: 0.03em; color: var(--muted); }
    .pill {
      display: inline-block;
      font-size: 12px;
      font-weight: 700;
      padding: 3px 8px;
      border-radius: 999px;
      border: 1px solid transparent;
    }
    .status-completed { color: var(--good); border-color: #9fceaa; background: #eaf8ee; }
    .status-in_progress { color: var(--warn); border-color: #ddc278; background: #fff6dd; }
    .status-failed { color: var(--bad); border-color: #e0a5a5; background: #fdeaea; }
    .muted { color: var(--muted); }
    @media (max-width: 900px) {
      .kpis { grid-template-columns: repeat(2, minmax(0, 1fr)); }
      h1 { font-size: 28px; }
      th, td { font-size: 13px; }
    }
  </style>
</head>
<body>
<main>
  <div class="top-actions">
    <a class="back-link" href="admin.php">Tilbake til hovedsiden</a>
  </div>
  <h1>Digital Detox: detaljert statistikk</h1>

  <section class="card">
    <form method="get">
      <label>
        Fra dato
        <input type="date" name="from" value="<?= h($fromDate) ?>">
      </label>
      <label>
        Til dato
        <input type="date" name="to" value="<?= h($toDate) ?>">
      </label>
      <button type="submit">Oppdater statistikk</button>
      <div class="muted">Maks 31 dager per visning.</div>
    </form>
  </section>

  <section class="card">
    <p class="muted" style="margin-top:0;">
      Regler: Digital Detox teller kun for telefoner som er levert inn for 09:30 (9:30 AM) og fortsatt er innlevert ved 18:30 (6:30 PM).
    </p>
    <div class="kpis">
      <div class="kpi"><div class="label">Kvalifiserte</div><div class="value"><?= $totalEligible ?></div></div>
      <div class="kpi"><div class="label">Fullført</div><div class="value"><?= $totalCompleted ?></div></div>
      <div class="kpi"><div class="label">Pågår</div><div class="value"><?= $totalInProgress ?></div></div>
      <div class="kpi"><div class="label">Brutt</div><div class="value"><?= $totalFailed ?></div></div>
      <div class="kpi"><div class="label">Suksessrate</div><div class="value"><?= number_format($successRate, 1, ',', ' ') ?>%</div></div>
    </div>
  </section>

  <section class="card">
    <h2>Dag for dag</h2>
    <table>
      <thead>
      <tr>
        <th>Dag</th>
        <th>Kvalifiserte</th>
        <th>Fullført</th>
        <th>Pågår</th>
        <th>Brutt</th>
        <th>Suksessrate</th>
      </tr>
      </thead>
      <tbody>
      <?php foreach (array_reverse($summary, true) as $day => $stats): ?>
        <?php $dayRate = $stats['eligible'] > 0 ? round(($stats['completed'] / $stats['eligible']) * 100, 1) : 0.0; ?>
        <tr>
          <td><?= h($day) ?></td>
          <td><?= $stats['eligible'] ?></td>
          <td><?= $stats['completed'] ?></td>
          <td><?= $stats['in_progress'] ?></td>
          <td><?= $stats['failed'] ?></td>
          <td><?= number_format($dayRate, 1, ',', ' ') ?>%</td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </section>

  <section class="card">
    <h2>Fordeling per rolle</h2>
    <table>
      <thead>
      <tr>
        <th>Rolle</th>
        <th>Kvalifiserte</th>
        <th>Fullført</th>
        <th>Pågår</th>
        <th>Brutt</th>
        <th>Suksessrate</th>
      </tr>
      </thead>
      <tbody>
      <?php if ($roleSummary === []): ?>
        <tr><td colspan="6" class="muted">Ingen data i valgt periode.</td></tr>
      <?php else: ?>
        <?php foreach ($roleSummary as $role => $stats): ?>
          <?php $roleRate = $stats['eligible'] > 0 ? round(($stats['completed'] / $stats['eligible']) * 100, 1) : 0.0; ?>
          <tr>
            <td><?= h($role) ?></td>
            <td><?= $stats['eligible'] ?></td>
            <td><?= $stats['completed'] ?></td>
            <td><?= $stats['in_progress'] ?></td>
            <td><?= $stats['failed'] ?></td>
            <td><?= number_format($roleRate, 1, ',', ' ') ?>%</td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
      </tbody>
    </table>
  </section>

  <section class="card">
    <h2>Detaljer per deltaker</h2>
    <table>
      <thead>
      <tr>
        <th>Dag</th>
        <th>Navn</th>
        <th>QR</th>
        <th>Rolle</th>
        <th>Innlevert</th>
        <th>Utlevert</th>
        <th>Skjermfri tid</th>
        <th>Status</th>
      </tr>
      </thead>
      <tbody>
      <?php if ($details === []): ?>
        <tr><td colspan="8" class="muted">Ingen deltakere med Digital Detox-data i valgt periode.</td></tr>
      <?php else: ?>
        <?php foreach ($details as $item): ?>
          <tr>
            <td><?= h($item['day']) ?></td>
            <td><?= h($item['name']) ?></td>
            <td><?= h($item['qr_code']) ?></td>
            <td><?= h($item['participant_type']) ?></td>
            <td><?= h($item['first_checkin']) ?></td>
            <td><?= $item['last_checkout'] !== '' ? h($item['last_checkout']) : '<span class="muted">-</span>' ?></td>
            <td><?= h(format_duration($item['duration_seconds'])) ?></td>
            <td><span class="pill status-<?= h($item['status']) ?>"><?= h(status_label($item['status'])) ?></span></td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
      </tbody>
    </table>
  </section>
</main>
</body>
</html>