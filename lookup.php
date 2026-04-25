<?php
require __DIR__ . '/db.php';
header('Content-Type: application/json');

function find_active_session(PDO $pdo, int $participantId): ?array
{
    $stmt = $pdo->prepare("
        SELECT ps.id AS session_id, s.slot_number, ps.checkin_time
        FROM phone_sessions ps
        JOIN slots s ON s.id = ps.slot_id
        WHERE ps.participant_id = ?
          AND ps.status = 'checked_in'
        LIMIT 1
    ");
    $stmt->execute([$participantId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}

function total_screenfree_seconds(PDO $pdo, int $participantId): int
{
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(TIMESTAMPDIFF(SECOND, checkin_time, checkout_time)), 0) AS seconds
        FROM phone_sessions
        WHERE participant_id = ?
          AND checkout_time IS NOT NULL
    ");
    $stmt->execute([$participantId]);

    return (int)$stmt->fetch()['seconds'];
}

function participant_payload(array $p, ?array $active, int $total): array
{
    return [
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
    ];
}

$action = trim($_GET['action'] ?? '');

if ($action === 'search') {
    $q = trim($_GET['q'] ?? '');
    if (mb_strlen($q) < 2) {
        echo json_encode(['success' => true, 'items' => []]);
        exit;
    }

    $needle = '%' . $q . '%';
    $stmt = $pdo->prepare("
        SELECT id, qr_code, first_name, last_name, county, participant_type
        FROM participants
        WHERE qr_code LIKE ?
           OR CONCAT(first_name, ' ', last_name) LIKE ?
           OR CONCAT(last_name, ' ', first_name) LIKE ?
        ORDER BY first_name, last_name
        LIMIT 12
    ");
    $stmt->execute([$needle, $needle, $needle]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($items as &$row) {
        $row['name'] = trim($row['first_name'] . ' ' . $row['last_name']);
    }

    echo json_encode(['success' => true, 'items' => $items]);
    exit;
}

$qr = trim($_GET['qr'] ?? '');
$participantId = (int)($_GET['participant_id'] ?? 0);

if ($qr === '' && $participantId <= 0) {
    echo json_encode(['found' => false, 'error' => 'missing_identifier']);
    exit;
}

$stmt = null;
if ($participantId > 0) {
    $stmt = $pdo->prepare("
        SELECT *
        FROM participants
        WHERE id = ?
        LIMIT 1
    ");
    $stmt->execute([$participantId]);
} else {
    $stmt = $pdo->prepare("
        SELECT *
        FROM participants
        WHERE qr_code = ?
        LIMIT 1
    ");
    $stmt->execute([$qr]);
}

$p = $stmt->fetch();

if (!$p) {
    echo json_encode(['found' => false]);
    exit;
}

$active = find_active_session($pdo, (int)$p['id']);
$total = total_screenfree_seconds($pdo, (int)$p['id']);

echo json_encode(participant_payload($p, $active, $total));
