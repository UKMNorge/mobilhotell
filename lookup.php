<?php
require __DIR__ . '/db.php';
header('Content-Type: application/json');

$qr = trim($_GET['qr'] ?? '');

if ($qr === '') {
    echo json_encode(['found' => false, 'error' => 'missing_qr']);
    exit;
}

$stmt = $pdo->prepare("
    SELECT *
    FROM participants
    WHERE qr_code = ?
    LIMIT 1
");
$stmt->execute([$qr]);
$p = $stmt->fetch();

if (!$p) {
    echo json_encode(['found' => false]);
    exit;
}

$stmt = $pdo->prepare("
    SELECT ps.id AS session_id, s.slot_number, ps.checkin_time
    FROM phone_sessions ps
    JOIN slots s ON s.id = ps.slot_id
    WHERE ps.participant_id = ?
      AND ps.status = 'checked_in'
    LIMIT 1
");
$stmt->execute([$p['id']]);
$active = $stmt->fetch();

$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(TIMESTAMPDIFF(SECOND, checkin_time, checkout_time)), 0) AS seconds
    FROM phone_sessions
    WHERE participant_id = ?
      AND checkout_time IS NOT NULL
");
$stmt->execute([$p['id']]);
$total = (int)$stmt->fetch()['seconds'];

echo json_encode([
    'found' => true,
    'participant_id' => $p['id'],
    'qr' => $p['qr_code'],
    'name' => $p['first_name'] . ' ' . $p['last_name'],
    'county' => $p['county'],
    'type' => $p['participant_type'],
    'image' => $p['image_path'] ?: 'images/default.png',
    'checked_in' => (bool)$active,
    'session_id' => $active['session_id'] ?? null,
    'slot' => $active['slot_number'] ?? null,
    'screenfree_seconds' => $total
]);
