<?php

declare(strict_types=1);

require __DIR__ . '/db.php';

header('Content-Type: application/json');

$pdo = db();
$action = trim((string)($_GET['action'] ?? ''));

if ($action === 'search') {
    $q = trim((string)($_GET['q'] ?? ''));
    if (mb_strlen($q) < 2) {
        echo json_encode(['success' => true, 'items' => []]);
        exit;
    }

    $needle = '%' . mb_strtolower($q) . '%';
    $stmt = $pdo->prepare("SELECT id, qr_code, first_name, last_name, county, participant_type
        FROM participants
        WHERE lower(qr_code) LIKE ?
           OR lower(first_name || ' ' || last_name) LIKE ?
           OR lower(last_name || ' ' || first_name) LIKE ?
        ORDER BY first_name, last_name
        LIMIT 15");
    $stmt->execute([$needle, $needle, $needle]);
    $items = $stmt->fetchAll();

    foreach ($items as &$row) {
        $row['name'] = trim($row['first_name'] . ' ' . $row['last_name']);
    }

    echo json_encode(['success' => true, 'items' => $items]);
    exit;
}

$qr = trim((string)($_GET['qr'] ?? ''));
$participantId = (int)($_GET['participant_id'] ?? 0);

if ($qr === '' && $participantId <= 0) {
    echo json_encode(['found' => false, 'error' => 'missing_identifier']);
    exit;
}

if ($participantId > 0) {
    $pStmt = $pdo->prepare('SELECT * FROM participants WHERE id = ? LIMIT 1');
    $pStmt->execute([$participantId]);
} else {
    $pStmt = $pdo->prepare('SELECT * FROM participants WHERE qr_code = ? LIMIT 1');
    $pStmt->execute([$qr]);
}

$p = $pStmt->fetch();
if (!$p) {
    echo json_encode(['found' => false]);
    exit;
}

$activeStmt = $pdo->prepare("SELECT ps.id AS session_id, s.slot_number
    FROM phone_sessions ps
    JOIN slots s ON s.id = ps.slot_id
    WHERE ps.participant_id = ?
      AND ps.status = 'checked_in'
    LIMIT 1");
$activeStmt->execute([(int)$p['id']]);
$active = $activeStmt->fetch();

$totalStmt = $pdo->prepare("SELECT COALESCE(SUM(
        CASE
            WHEN status = 'checked_out' AND checkout_time IS NOT NULL THEN strftime('%s', checkout_time) - strftime('%s', checkin_time)
            WHEN status = 'checked_in' THEN strftime('%s', datetime('now')) - strftime('%s', checkin_time)
            ELSE 0
        END
    ), 0) AS seconds
    FROM phone_sessions
    WHERE participant_id = ?");
$totalStmt->execute([(int)$p['id']]);
$total = (int)$totalStmt->fetchColumn();

echo json_encode([
    'found' => true,
    'participant_id' => (int)$p['id'],
    'qr' => $p['qr_code'],
    'name' => trim($p['first_name'] . ' ' . $p['last_name']),
    'county' => $p['county'],
    'type' => $p['participant_type'],
    'image' => $p['image_path'] ?: 'images/default.png',
    'checked_in' => (bool)$active,
    'session_id' => $active ? (int)$active['session_id'] : null,
    'slot' => $active['slot_number'] ?? null,
    'screenfree_seconds' => $total
]);
