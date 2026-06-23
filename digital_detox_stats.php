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

function parse_prize_scope(string $value): string
{
  $value = strtolower(trim($value));
  if ($value === 'filter') {
    return 'filter';
  }

  return 'all';
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

  function sort_direction(string $value, string $fallback = 'desc'): string
  {
    $value = strtolower(trim($value));
    if ($value === 'asc' || $value === 'desc') {
      return $value;
    }

    return $fallback;
  }

  function compare_values(mixed $a, mixed $b, string $type): int
  {
    if ($type === 'int') {
      return ((int)$a) <=> ((int)$b);
    }

    return strcmp((string)$a, (string)$b);
  }

  function sort_rows(array &$rows, string $sortKey, string $direction, array $allowedSorts, array $fallbackOrder = []): void
  {
    if (!isset($allowedSorts[$sortKey])) {
      $sortKey = array_key_first($allowedSorts) ?: '';
    }

    $sortType = (string)($allowedSorts[$sortKey]['type'] ?? 'string');
    $sortOrder = strtolower((string)($allowedSorts[$sortKey]['default_dir'] ?? 'asc'));
    $dir = sort_direction($direction, $sortOrder);

    usort($rows, static function (array $a, array $b) use ($sortKey, $sortType, $dir, $fallbackOrder): int {
      $primary = compare_values($a[$sortKey] ?? '', $b[$sortKey] ?? '', $sortType);
      if ($primary !== 0) {
        return $dir === 'asc' ? $primary : -$primary;
      }

      foreach ($fallbackOrder as $fallback) {
        $key = (string)($fallback['key'] ?? '');
        if ($key === '') {
          continue;
        }

        $type = (string)($fallback['type'] ?? 'string');
        $fallbackDir = sort_direction((string)($fallback['dir'] ?? 'asc'), 'asc');
        $cmp = compare_values($a[$key] ?? '', $b[$key] ?? '', $type);
        if ($cmp !== 0) {
          return $fallbackDir === 'asc' ? $cmp : -$cmp;
        }
      }

      return 0;
    });
  }

  function build_sort_link(array $query, string $sortParam, string $dirParam, string $column, string $currentSort, string $currentDir): string
  {
    $nextDir = 'desc';
    if ($currentSort === $column && strtolower($currentDir) === 'desc') {
      $nextDir = 'asc';
    }

    $query[$sortParam] = $column;
    $query[$dirParam] = $nextDir;

    return '?' . http_build_query($query);
  }

  function sort_indicator(string $column, string $currentSort, string $currentDir): string
  {
    if ($column !== $currentSort) {
      return '';
    }

    return strtolower($currentDir) === 'asc' ? ' ▲' : ' ▼';
  }

  function overlap_seconds(string $start, string $end, string $windowStart, string $windowEnd): ?int
  {
    $startTs = strtotime($start);
    $endTs = strtotime($end);
    $windowStartTs = strtotime($windowStart);
    $windowEndTs = strtotime($windowEnd);

    if ($startTs === false || $endTs === false || $windowStartTs === false || $windowEndTs === false) {
      return null;
    }

    $fromTs = max($startTs, $windowStartTs);
    $toTs = min($endTs, $windowEndTs);

    return max(0, $toTs - $fromTs);
  }

  function detox_overlap_for_period(string $start, string $end, string $fromDate, string $toDate): ?int
  {
    $startTs = strtotime($start);
    $endTs = strtotime($end);
    $periodStartTs = strtotime($fromDate . ' 00:00:00');
    $periodEndExclusiveTs = strtotime($toDate . ' +1 day 00:00:00');

    if ($startTs === false || $endTs === false || $periodStartTs === false || $periodEndExclusiveTs === false) {
      return null;
    }

    $clippedStartTs = max($startTs, $periodStartTs);
    $clippedEndTs = min($endTs, $periodEndExclusiveTs);
    if ($clippedEndTs <= $clippedStartTs) {
      return 0;
    }

    $total = 0;
    $cursorDayTs = strtotime(date('Y-m-d 00:00:00', $clippedStartTs));
    if ($cursorDayTs === false) {
      return null;
    }

    while ($cursorDayTs < $clippedEndTs) {
      $day = date('Y-m-d', $cursorDayTs);
      $detoxStartTs = strtotime($day . ' 09:30:00');
      $detoxEndTs = strtotime($day . ' 18:30:00');
      if ($detoxStartTs !== false && $detoxEndTs !== false) {
        $fromTs = max($clippedStartTs, $detoxStartTs);
        $toTs = min($clippedEndTs, $detoxEndTs);
        if ($toTs > $fromTs) {
          $total += ($toTs - $fromTs);
        }
      }

      $nextDayTs = strtotime('+1 day', $cursorDayTs);
      if ($nextDayTs === false || $nextDayTs <= $cursorDayTs) {
        break;
      }
      $cursorDayTs = $nextDayTs;
    }

    return $total;
  }

function overlap_for_period(string $start, string $end, string $fromDate, string $toDate): ?int
{
  $periodStart = $fromDate . ' 00:00:00';
  $periodEndExclusive = date('Y-m-d 00:00:00', strtotime($toDate . ' +1 day'));

  if ($periodEndExclusive === false) {
    return null;
  }

  return overlap_seconds($start, $end, $periodStart, $periodEndExclusive);
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

function compute_duration_seconds(array $row, string $day, ?string $now = null): ?int
{
    $firstCheckin = (string)($row['first_checkin'] ?? '');
    if ($firstCheckin === '') {
        return null;
    }

    $end = (string)($row['last_checkout'] ?? '');
    if ($end === '') {
      $end = $now !== null && $now !== '' ? $now : ($day . ' 18:30:00');
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
$bounds = $pdo->query("SELECT DATE_FORMAT(MIN(checkin_time), '%Y-%m-%d') AS first_session_day FROM phone_sessions")->fetch();

$today = (string)($clock['today_local'] ?? date('Y-m-d'));
$firstSessionDay = (string)($bounds['first_session_day'] ?? $today);
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $firstSessionDay)) {
  $firstSessionDay = $today;
}

$fromDefault = $firstSessionDay;
$toDefault = $today;

$fromDate = parse_date_input((string)($_GET['from'] ?? ''), $fromDefault);
$toDate = parse_date_input((string)($_GET['to'] ?? ''), $toDefault);
$prizeScope = parse_prize_scope((string)($_GET['prize_scope'] ?? 'all'));
$printTop4Status = trim((string)($_GET['print_top4_status'] ?? ''));
$printTop4Message = trim((string)($_GET['print_top4_message'] ?? ''));
$printTop4Count = max(0, (int)($_GET['print_top4_count'] ?? 0));
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
$overallByParticipant = [];

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

    $durationSeconds = compute_duration_seconds($row, $day, $now);

    $details[] = [
      'participant_id' => (int)$row['participant_id'],
        'day' => $day,
        'name' => trim((string)$row['first_name'] . ' ' . (string)$row['last_name']),
        'qr_code' => (string)$row['qr_code'],
        'participant_type' => $role,
        'first_checkin' => (string)$row['first_checkin'],
        'last_checkout' => (string)($row['last_checkout'] ?? ''),
        'status' => $status,
        'duration_seconds' => $durationSeconds,
    ];

    $participantKey = (string)$row['participant_id'];
    if (!isset($overallByParticipant[$participantKey])) {
      $overallByParticipant[$participantKey] = [
        'name' => trim((string)$row['first_name'] . ' ' . (string)$row['last_name']),
        'qr_code' => (string)$row['qr_code'],
        'participant_type' => $role,
        'total_seconds' => 0,
        'days_completed' => 0,
        'days_eligible' => 0,
      ];
    }

    $overallByParticipant[$participantKey]['days_eligible']++;
    if ($status === 'completed') {
      $overallByParticipant[$participantKey]['days_completed']++;
    }
}

  $detailSorts = [
    'day' => ['type' => 'string', 'default_dir' => 'desc'],
    'name' => ['type' => 'string', 'default_dir' => 'asc'],
    'qr_code' => ['type' => 'string', 'default_dir' => 'asc'],
    'participant_type' => ['type' => 'string', 'default_dir' => 'asc'],
    'first_checkin' => ['type' => 'string', 'default_dir' => 'desc'],
    'last_checkout' => ['type' => 'string', 'default_dir' => 'desc'],
    'duration_seconds' => ['type' => 'int', 'default_dir' => 'desc'],
    'status' => ['type' => 'string', 'default_dir' => 'asc'],
  ];

  $detailSort = (string)($_GET['details_sort'] ?? 'day');
  if (!isset($detailSorts[$detailSort])) {
    $detailSort = 'day';
  }

  $detailDir = sort_direction((string)($_GET['details_dir'] ?? ''), (string)$detailSorts[$detailSort]['default_dir']);
  sort_rows($details, $detailSort, $detailDir, $detailSorts, [
    ['key' => 'status', 'type' => 'string', 'dir' => 'asc'],
    ['key' => 'day', 'type' => 'string', 'dir' => 'desc'],
    ['key' => 'name', 'type' => 'string', 'dir' => 'asc'],
  ]);

  $rangeStart = $fromDate . ' 00:00:00';
  $rangeEndExclusive = date('Y-m-d 00:00:00', strtotime($toDate . ' +1 day'));

  $singleStmt = $pdo->prepare("SELECT
    ps.id,
    ps.participant_id,
    p.qr_code,
    p.first_name,
    p.last_name,
    p.participant_type,
    ps.checkin_time,
    ps.checkout_time,
    ps.status,
    ps.delivery_type,
    s.slot_number
    FROM phone_sessions ps
    JOIN participants p ON p.id = ps.participant_id
    LEFT JOIN slots s ON s.id = ps.slot_id
    WHERE ps.checkin_time < ?
    AND (ps.checkout_time IS NULL OR ps.checkout_time >= ?)
    ORDER BY ps.checkin_time DESC, ps.id DESC");

  $singleStmt->execute([$rangeEndExclusive, $rangeStart]);
  $singleRows = $singleStmt->fetchAll();
  $singleDeliveries = [];
  $detoxTotalsByParticipant = [];

  foreach ($singleRows as $row) {
    $checkin = (string)$row['checkin_time'];
    $checkout = (string)($row['checkout_time'] ?? '');
    $effectiveEnd = $checkout !== '' ? $checkout : $now;

    $day = substr($checkin, 0, 10);
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $day)) {
      $day = '';
    }

    $detoxSeconds = null;
    if ($day !== '') {
      $detoxSeconds = overlap_seconds(
        $checkin,
        $effectiveEnd,
        $day . ' 09:30:00',
        $day . ' 18:30:00'
      );
    }

    $detoxSecondsPeriod = detox_overlap_for_period($checkin, $effectiveEnd, $fromDate, $toDate);

    $participantKey = (string)$row['participant_id'];
    if (!isset($detoxTotalsByParticipant[$participantKey])) {
      $detoxTotalsByParticipant[$participantKey] = 0;
    }
    $detoxTotalsByParticipant[$participantKey] += max(0, (int)$detoxSecondsPeriod);

    $singleDeliveries[] = [
      'id' => (int)$row['id'],
      'participant_id' => (int)$row['participant_id'],
      'day' => $day,
      'name' => trim((string)$row['first_name'] . ' ' . (string)$row['last_name']),
      'qr_code' => (string)$row['qr_code'],
      'participant_type' => trim((string)$row['participant_type']) !== '' ? (string)$row['participant_type'] : 'Ukjent',
      'checkin_time' => $checkin,
      'checkout_time' => $checkout,
      'status' => (string)$row['status'],
      'delivery_type' => (string)$row['delivery_type'],
      'slot_number' => (string)($row['slot_number'] ?? ''),
      'session_seconds' => overlap_seconds($checkin, $effectiveEnd, $checkin, $effectiveEnd),
      'detox_seconds' => $detoxSecondsPeriod,
    ];
  }

  $deliverySorts = [
    'day' => ['type' => 'string', 'default_dir' => 'desc'],
    'name' => ['type' => 'string', 'default_dir' => 'asc'],
    'checkin_time' => ['type' => 'string', 'default_dir' => 'desc'],
    'checkout_time' => ['type' => 'string', 'default_dir' => 'desc'],
    'session_seconds' => ['type' => 'int', 'default_dir' => 'desc'],
    'detox_seconds' => ['type' => 'int', 'default_dir' => 'desc'],
    'participant_type' => ['type' => 'string', 'default_dir' => 'asc'],
    'slot_number' => ['type' => 'string', 'default_dir' => 'asc'],
    'status' => ['type' => 'string', 'default_dir' => 'asc'],
    'delivery_type' => ['type' => 'string', 'default_dir' => 'asc'],
  ];

  $deliverySort = (string)($_GET['deliveries_sort'] ?? 'checkin_time');
  if (!isset($deliverySorts[$deliverySort])) {
    $deliverySort = 'checkin_time';
  }

  $deliveryDir = sort_direction((string)($_GET['deliveries_dir'] ?? ''), (string)$deliverySorts[$deliverySort]['default_dir']);
  sort_rows($singleDeliveries, $deliverySort, $deliveryDir, $deliverySorts, [
    ['key' => 'checkin_time', 'type' => 'string', 'dir' => 'desc'],
    ['key' => 'name', 'type' => 'string', 'dir' => 'asc'],
  ]);

  $prizeFromDate = $firstSessionDay;
  $prizeToDate = $today;
  $prizeRangeStart = $prizeFromDate . ' 00:00:00';
  $prizeRangeEndExclusive = date('Y-m-d 00:00:00', strtotime($prizeToDate . ' +1 day'));

  $prizeStmt = $pdo->prepare("SELECT
    ps.participant_id,
    p.qr_code,
    p.first_name,
    p.last_name,
    p.participant_type,
    ps.checkin_time,
    ps.checkout_time
    FROM phone_sessions ps
    JOIN participants p ON p.id = ps.participant_id
    WHERE ps.checkin_time < ?
    AND (ps.checkout_time IS NULL OR ps.checkout_time >= ?)
    ORDER BY ps.checkin_time DESC, ps.id DESC");

  if ($prizeScope === 'filter') {
    $prizeFromDate = $fromDate;
    $prizeToDate = $toDate;
  }
  $prizeRangeStart = $prizeFromDate . ' 00:00:00';
  $prizeRangeEndExclusive = date('Y-m-d 00:00:00', strtotime($prizeToDate . ' +1 day'));

  $prizeStmt->execute([$prizeRangeEndExclusive, $prizeRangeStart]);
  $prizeRows = $prizeStmt->fetchAll();
  $leaderboardByParticipant = [];

  foreach ($prizeRows as $row) {
    $participantKey = (string)$row['participant_id'];
    if (!isset($leaderboardByParticipant[$participantKey])) {
      $leaderboardByParticipant[$participantKey] = [
        'name' => trim((string)$row['first_name'] . ' ' . (string)$row['last_name']),
        'qr_code' => (string)$row['qr_code'],
        'participant_type' => trim((string)$row['participant_type']) !== '' ? (string)$row['participant_type'] : 'Ukjent',
        'total_seconds' => 0,
        'checkins' => 0,
        'checkouts' => 0,
      ];
    }

    $effectiveEnd = (string)($row['checkout_time'] ?? '') !== '' ? (string)$row['checkout_time'] : $now;
    $leaderboardByParticipant[$participantKey]['checkins']++;
    if ((string)($row['checkout_time'] ?? '') !== '') {
      $leaderboardByParticipant[$participantKey]['checkouts']++;
    }
    $leaderboardByParticipant[$participantKey]['total_seconds'] += max(
      0,
      (int)overlap_for_period((string)$row['checkin_time'], $effectiveEnd, $prizeFromDate, $prizeToDate)
    );
  }

  $leaderboard = array_values($leaderboardByParticipant);

  usort($leaderboard, static function (array $a, array $b): int {
    if ((int)$a['total_seconds'] !== (int)$b['total_seconds']) {
      return ((int)$b['total_seconds']) <=> ((int)$a['total_seconds']);
    }

    return strcmp((string)$a['name'], (string)$b['name']);
  });

  $topPrizeCandidates = array_slice($leaderboard, 0, 4);

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
    select {
      border: 1px solid #bcc8be;
      border-radius: 8px;
      padding: 8px 10px;
      font-size: 16px;
      min-width: 220px;
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
    th a { color: inherit; text-decoration: none; }
    th a:hover { text-decoration: underline; }
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
    .notice {
      border-radius: 8px;
      padding: 10px 12px;
      margin-bottom: 12px;
      border: 1px solid transparent;
      font-size: 14px;
      font-weight: 600;
    }
    .notice-success {
      color: #1f5e2a;
      background: #eaf8ee;
      border-color: #9fceaa;
    }
    .notice-error {
      color: #7d2020;
      background: #fdeaea;
      border-color: #e0a5a5;
    }
    .row-actions {
      display: flex;
      gap: 10px;
      align-items: center;
      justify-content: space-between;
      flex-wrap: wrap;
      margin-bottom: 8px;
    }
    .small-btn {
      text-decoration: none;
      border: 1px solid #bcc8be;
      border-radius: 8px;
      background: #fff;
      color: var(--ink);
      font-size: 14px;
      font-weight: 700;
      padding: 7px 12px;
      display: inline-block;
    }
    .small-btn:hover {
      background: #f4f8f5;
    }
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

  <?php if ($printTop4Status === 'ok'): ?>
    <div class="notice notice-success">Topp 4 sendt til kvitteringsskriver (<?= $printTop4Count ?> deltakere).</div>
  <?php elseif ($printTop4Status === 'error' && $printTop4Message !== ''): ?>
    <div class="notice notice-error">Utskrift av topp 4 feilet: <?= h($printTop4Message) ?></div>
  <?php endif; ?>

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
      <label>
        Topp 4-periode
        <select name="prize_scope">
          <option value="all" <?= $prizeScope === 'all' ? 'selected' : '' ?>>Hele perioden</option>
          <option value="filter" <?= $prizeScope === 'filter' ? 'selected' : '' ?>>Valgt periode</option>
        </select>
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
    <h2>Ekstra premie: topp 4 totalt skjermfri tid</h2>
    <div class="row-actions">
      <p class="muted" style="margin:0;">Beregnet for <?= $prizeScope === 'all' ? 'hele perioden' : 'valgt periode' ?>: <?= h($prizeFromDate) ?> til <?= h($prizeToDate) ?>.</p>
      <a class="small-btn" href="print_top4.php?<?= h(http_build_query(['from' => $fromDate, 'to' => $toDate, 'prize_scope' => $prizeScope])) ?>">Skriv ut topp 4</a>
    </div>
    <table>
      <thead>
      <tr>
        <th>#</th>
        <th>Navn</th>
        <th>QR</th>
        <th>Rolle</th>
        <th>Innleveringer</th>
        <th>Utleveringer</th>
        <th>Total skjermfri tid</th>
      </tr>
      </thead>
      <tbody>
      <?php if ($topPrizeCandidates === []): ?>
        <tr><td colspan="7" class="muted">Ingen kvalifiserte deltakere i perioden.</td></tr>
      <?php else: ?>
        <?php foreach ($topPrizeCandidates as $index => $candidate): ?>
          <tr>
            <td><?= (int)$index + 1 ?></td>
            <td><?= h((string)$candidate['name']) ?></td>
            <td><?= h((string)$candidate['qr_code']) ?></td>
            <td><?= h((string)$candidate['participant_type']) ?></td>
            <td><?= (int)$candidate['checkins'] ?></td>
            <td><?= (int)$candidate['checkouts'] ?></td>
            <td><strong><?= h(format_duration((int)$candidate['total_seconds'])) ?></strong></td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
      </tbody>
    </table>
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
    <?php $detailQueryBase = [
        'from' => $fromDate,
        'to' => $toDate,
      'prize_scope' => $prizeScope,
        'details_sort' => $detailSort,
        'details_dir' => $detailDir,
        'deliveries_sort' => $deliverySort,
        'deliveries_dir' => $deliveryDir,
    ]; ?>
    <table>
      <thead>
      <tr>
        <th><a href="<?= h(build_sort_link($detailQueryBase, 'details_sort', 'details_dir', 'day', $detailSort, $detailDir)) ?>">Dag<?= h(sort_indicator('day', $detailSort, $detailDir)) ?></a></th>
        <th><a href="<?= h(build_sort_link($detailQueryBase, 'details_sort', 'details_dir', 'name', $detailSort, $detailDir)) ?>">Navn<?= h(sort_indicator('name', $detailSort, $detailDir)) ?></a></th>
        <th>QR</th>
        <th><a href="<?= h(build_sort_link($detailQueryBase, 'details_sort', 'details_dir', 'participant_type', $detailSort, $detailDir)) ?>">Rolle<?= h(sort_indicator('participant_type', $detailSort, $detailDir)) ?></a></th>
        <th><a href="<?= h(build_sort_link($detailQueryBase, 'details_sort', 'details_dir', 'first_checkin', $detailSort, $detailDir)) ?>">Innlevert<?= h(sort_indicator('first_checkin', $detailSort, $detailDir)) ?></a></th>
        <th><a href="<?= h(build_sort_link($detailQueryBase, 'details_sort', 'details_dir', 'last_checkout', $detailSort, $detailDir)) ?>">Utlevert<?= h(sort_indicator('last_checkout', $detailSort, $detailDir)) ?></a></th>
        <th><a href="<?= h(build_sort_link($detailQueryBase, 'details_sort', 'details_dir', 'duration_seconds', $detailSort, $detailDir)) ?>">Skjermfri tid<?= h(sort_indicator('duration_seconds', $detailSort, $detailDir)) ?></a></th>
        <th><a href="<?= h(build_sort_link($detailQueryBase, 'details_sort', 'details_dir', 'status', $detailSort, $detailDir)) ?>">Status<?= h(sort_indicator('status', $detailSort, $detailDir)) ?></a></th>
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

  <section class="card">
    <h2>Enkeltinnleveringer (alle sesjoner)</h2>
    <?php $deliveryQueryBase = [
        'from' => $fromDate,
        'to' => $toDate,
      'prize_scope' => $prizeScope,
        'details_sort' => $detailSort,
        'details_dir' => $detailDir,
        'deliveries_sort' => $deliverySort,
        'deliveries_dir' => $deliveryDir,
    ]; ?>
    <table>
      <thead>
      <tr>
        <th><a href="<?= h(build_sort_link($deliveryQueryBase, 'deliveries_sort', 'deliveries_dir', 'day', $deliverySort, $deliveryDir)) ?>">Dag<?= h(sort_indicator('day', $deliverySort, $deliveryDir)) ?></a></th>
        <th><a href="<?= h(build_sort_link($deliveryQueryBase, 'deliveries_sort', 'deliveries_dir', 'name', $deliverySort, $deliveryDir)) ?>">Navn<?= h(sort_indicator('name', $deliverySort, $deliveryDir)) ?></a></th>
        <th>QR</th>
        <th><a href="<?= h(build_sort_link($deliveryQueryBase, 'deliveries_sort', 'deliveries_dir', 'participant_type', $deliverySort, $deliveryDir)) ?>">Rolle<?= h(sort_indicator('participant_type', $deliverySort, $deliveryDir)) ?></a></th>
        <th><a href="<?= h(build_sort_link($deliveryQueryBase, 'deliveries_sort', 'deliveries_dir', 'delivery_type', $deliverySort, $deliveryDir)) ?>">Type<?= h(sort_indicator('delivery_type', $deliverySort, $deliveryDir)) ?></a></th>
        <th><a href="<?= h(build_sort_link($deliveryQueryBase, 'deliveries_sort', 'deliveries_dir', 'slot_number', $deliverySort, $deliveryDir)) ?>">Plass<?= h(sort_indicator('slot_number', $deliverySort, $deliveryDir)) ?></a></th>
        <th><a href="<?= h(build_sort_link($deliveryQueryBase, 'deliveries_sort', 'deliveries_dir', 'checkin_time', $deliverySort, $deliveryDir)) ?>">Innlevert<?= h(sort_indicator('checkin_time', $deliverySort, $deliveryDir)) ?></a></th>
        <th><a href="<?= h(build_sort_link($deliveryQueryBase, 'deliveries_sort', 'deliveries_dir', 'checkout_time', $deliverySort, $deliveryDir)) ?>">Utlevert<?= h(sort_indicator('checkout_time', $deliverySort, $deliveryDir)) ?></a></th>
        <th><a href="<?= h(build_sort_link($deliveryQueryBase, 'deliveries_sort', 'deliveries_dir', 'session_seconds', $deliverySort, $deliveryDir)) ?>">Varighet</a><?= h(sort_indicator('session_seconds', $deliverySort, $deliveryDir)) ?></th>
        <th><a href="<?= h(build_sort_link($deliveryQueryBase, 'deliveries_sort', 'deliveries_dir', 'detox_seconds', $deliverySort, $deliveryDir)) ?>">Detox-tid i valgt periode<?= h(sort_indicator('detox_seconds', $deliverySort, $deliveryDir)) ?></a></th>
        <th><a href="<?= h(build_sort_link($deliveryQueryBase, 'deliveries_sort', 'deliveries_dir', 'status', $deliverySort, $deliveryDir)) ?>">Status<?= h(sort_indicator('status', $deliverySort, $deliveryDir)) ?></a></th>
      </tr>
      </thead>
      <tbody>
      <?php if ($singleDeliveries === []): ?>
        <tr><td colspan="11" class="muted">Ingen innleveringer i valgt periode.</td></tr>
      <?php else: ?>
        <?php foreach ($singleDeliveries as $delivery): ?>
          <tr>
            <td><?= h((string)$delivery['day']) ?></td>
            <td><?= h((string)$delivery['name']) ?></td>
            <td><?= h((string)$delivery['qr_code']) ?></td>
            <td><?= h((string)$delivery['participant_type']) ?></td>
            <td><?= h((string)$delivery['delivery_type']) ?></td>
            <td><?= h((string)$delivery['slot_number']) ?></td>
            <td><?= h((string)$delivery['checkin_time']) ?></td>
            <td><?= (string)$delivery['checkout_time'] !== '' ? h((string)$delivery['checkout_time']) : '<span class="muted">-</span>' ?></td>
            <td><?= h(format_duration((int)$delivery['session_seconds'])) ?></td>
            <td><?= h(format_duration(isset($delivery['detox_seconds']) ? (int)$delivery['detox_seconds'] : null)) ?></td>
            <td><?= h((string)$delivery['status']) ?></td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
      </tbody>
    </table>
  </section>
</main>
</body>
</html>